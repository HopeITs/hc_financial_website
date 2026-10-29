<?php

use App\Enums\RoleId;
use App\Livewire\MicroLoans;
use App\Livewire\Salaries;
use App\Livewire\Transfers;
use App\Models\AllocationRate;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\MicroLoan;
use App\Models\PayrollSplit;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (require database_path('migrations/2026_10_26_090000_create_gl_payroll_splits_table.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // This test's own project, the Aleppo general accounts and two budget lines; the chart seeder adds no projects.
    GlProject::query()->create([
        'code' => 'F2F', 'name' => 'Family to Family 2026', 'donor' => 'Caritas Poland', 'restriction' => 'With donor restrictions',
        'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false,
    ]);
    foreach (['GEN-ALP-USD' => 'USD', 'GEN-ALP-SYP' => 'SYP'] as $code => $currency) {
        GlProject::query()->create([
            'code' => $code, 'name' => 'Aleppo general '.$currency, 'donor' => 'General', 'restriction' => 'Without donor restrictions',
            'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => true,
        ]);
    }
    GlBudgetLine::query()->create(['key' => 'F2F|Staff', 'project_code' => 'F2F', 'name' => 'Staff', 'account_code' => '5101', 'budget' => 100000, 'currency' => 'USD']);
    GlBudgetLine::query()->create(['key' => 'F2F|Grants', 'project_code' => 'F2F', 'name' => 'Grants', 'account_code' => '5101', 'budget' => 100000, 'currency' => 'USD']);
});

function fdBUser(RoleId $role = RoleId::Accountant, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function fdBDraft(int $userId, string $screen): ?object
{
    return DB::table('web_form_drafts')->where('user_id', $userId)->where('screen', $screen)->first();
}

it('drafts a payroll split on salaries, restores the rows as text, and forgets it once the split is saved', function () {
    $ann = fdBUser();

    Livewire::actingAs($ann)->test(Salaries::class)
        ->set('tab', 'splits')->set('employeeId', '7')->set('employeeName', 'Rami')
        ->set('splitRows', [['budget_line' => 'F2F|Staff', 'percent' => '100']])
        ->call('saveDraft')
        ->assertDispatched('form-clean');

    expect(json_decode(fdBDraft($ann->getKey(), 'salaries')->payload, true))->toMatchArray(['tab' => 'splits', 'employeeId' => '7']);

    $page = Livewire::actingAs($ann)->test(Salaries::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->assertSet('employeeId', '')
        ->call('restoreDraft')
        ->assertSet('tab', 'splits')->assertSet('employeeId', '7')->assertSet('employeeName', 'Rami')
        ->assertSet('splitRows', [['budget_line' => 'F2F|Staff', 'percent' => '100']])
        ->assertSet('lines', [['budget_line' => '', 'amount' => '', 'percent' => '']]);

    $page->call('saveSplit')->assertSet('failures', []);

    expect(PayrollSplit::query()->count())->toBe(1)
        ->and(fdBDraft($ann->getKey(), 'salaries'))->toBeNull();
});

it('gives restored salary rows exactly their own fields', function () {
    $ann = fdBUser();
    DB::table('web_form_drafts')->insert([
        'user_id' => $ann->getKey(), 'screen' => 'salaries', 'saved_at' => now(),
        'payload' => json_encode(['splitRows' => [['budget_line' => 'F2F|Staff', 'percent' => 40, 'evil' => '<b>'], 'not a row'], 'lines' => []]),
    ]);

    $page = Livewire::actingAs($ann)->test(Salaries::class)->call('restoreDraft');

    expect($page->get('splitRows'))->toBe([['budget_line' => 'F2F|Staff', 'percent' => '40']])
        ->and($page->get('lines'))->toBe([['budget_line' => '', 'amount' => '', 'percent' => '']]);
});

it('drafts a rate proposal on transfers, restores it, and forgets it once the rate is proposed', function () {
    $ann = fdBUser();

    Livewire::actingAs($ann)->test(Transfers::class)
        ->set('rateCity', 'Aleppo')->set('rateValue', '14500')->set('rateFrom', '2026-03-01')->set('rateAverageCost', true)
        ->set('description', 'مسودة تحويل')
        ->call('saveDraft')
        ->assertDispatched('form-clean');

    $page = Livewire::actingAs($ann)->test(Transfers::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->assertSet('rateValue', '')
        ->call('restoreDraft')
        ->assertSet('rateValue', '14500')->assertSet('rateFrom', '2026-03-01')->assertSet('rateAverageCost', true)
        ->assertSet('description', 'مسودة تحويل');

    $page->call('propose')->assertSet('failures', []);

    expect(AllocationRate::query()->count())->toBe(1)
        ->and(fdBDraft($ann->getKey(), 'alloc'))->toBeNull();
});

it('drafts a loan on micro loans, restores it, and forgets it once the loan is opened', function () {
    $ann = fdBUser();
    $request = LedgerRequest::query()->create([
        'type' => 'purchase', 'project_code' => 'F2F', 'amount' => '5000.00', 'currency' => 'USD', 'status' => 'approved', 'requested_by' => 3,
    ]);

    Livewire::actingAs($ann)->test(MicroLoans::class)
        ->set('beneficiary', 'Huda')->set('project', 'F2F')->set('loanAmount', '1000')->set('grantAmount', '200')
        ->set('instalments', '4')->set('cashAccount', '1111')->set('budgetLine', 'F2F|Grants')->set('requestId', (string) $request->id)
        ->call('saveDraft')
        ->assertDispatched('form-clean');

    $page = Livewire::actingAs($ann)->test(MicroLoans::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->assertSet('beneficiary', '')
        ->call('restoreDraft')
        ->assertSet('beneficiary', 'Huda')->assertSet('loanAmount', '1000')->assertSet('instalments', '4')
        ->assertSet('budgetLine', 'F2F|Grants')->assertSet('requestId', (string) $request->id);

    $page->call('open')->assertSet('failures', []);

    expect(MicroLoan::query()->count())->toBe(1)
        ->and(fdBDraft($ann->getKey(), 'payments'))->toBeNull();
});

it('puts the leave warning and the draft button on salaries, transfers and micro loans', function () {
    $ann = fdBUser();

    foreach ([Salaries::class, Transfers::class, MicroLoans::class] as $screen) {
        Livewire::actingAs($ann)->test($screen)
            ->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('data-test="save-draft"')->assertSeeHtml('x-on:click="leaving()"');
    }
});

it('refuses a draft from someone who may not save on these screens', function () {
    $emp = fdBUser(RoleId::Employee, 'Emp');

    Livewire::actingAs($emp)->test(Transfers::class)->call('saveDraft')->assertForbidden();
});
