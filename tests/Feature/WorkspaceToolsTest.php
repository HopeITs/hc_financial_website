<?php

use App\Enums\RoleId;
use App\Livewire\Home;
use App\Livewire\HomeWaiting;
use App\Livewire\MultiEntry;
use App\Livewire\Requests;
use App\Livewire\SearchDialog;
use App\Livewire\SingleEntry;
use App\Livewire\Vouchers;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Home\WaitingWork;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Requests\RequestWorkflow;
use App\Services\Search\GlobalSearch;
use App\Support\Navigation;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();

    Schema::connection('legacy')->create('role_currency_limit', function ($table) {
        $table->integer('Role_ID');
        $table->integer('TillType_ID');
        $table->decimal('Approval_Limit', 18, 2);
    });

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // This test's own projects; the chart seeder adds no projects.
    GlProject::query()->create([
        'code' => 'F2F', 'name' => 'Family to Family 2026', 'donor' => 'Caritas Poland', 'restriction' => 'With donor restrictions',
        'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false, 'legacy_account_id' => 77,
    ]);
    GlProject::query()->create([
        'code' => 'EDU', 'name' => 'Education Homs', 'donor' => 'Other donor', 'restriction' => 'With donor restrictions',
        'city' => 'Homs', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false, 'legacy_account_id' => 88,
    ]);
    GlBudgetLine::query()->create([
        'key' => 'F2F|Maintenance', 'project_code' => 'F2F', 'donor' => 'Caritas Poland', 'name' => 'Maintenance',
        'account_code' => '5405', 'budget' => 10000, 'currency' => 'USD',
    ]);
    DB::connection('legacy')->table('account')->insert([['ID' => 77, 'SubProject_ID' => 7, 'Name' => 'F2F'], ['ID' => 88, 'SubProject_ID' => 8, 'Name' => 'EDU']]);
});

function wtUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** @return array<string, int> key => count */
function wtWaiting(User $user): array
{
    return collect(app(WaitingWork::class)->for($user))->mapWithKeys(fn (array $item) => [$item['key'] => $item['count']])->all();
}

// U-03

it('shows each role the work waiting for it, with the same rules as the bell', function () {
    $accountant = wtUser(RoleId::Accountant, 'Acc');
    $manager = wtUser(RoleId::FinancialManager, 'Fm');
    $employee = wtUser(RoleId::Employee, 'Emp');

    app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $accountant->getKey()]));
    $rejected = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $accountant->getKey()]));
    app(VoucherApprovals::class)->reject($rejected, $manager, 'No invoice');

    expect(wtWaiting($manager))->toMatchArray(['approve' => 1])
        ->and(wtWaiting($accountant))->toMatchArray(['rejected' => 1])
        ->and(wtWaiting($accountant))->not->toHaveKey('approve')
        ->and(wtWaiting($employee))->toBe([]);
});

it('counts requests to decide and the person\'s own requests still waiting', function () {
    $pm = wtUser(RoleId::ProjectManager, 'Pam');
    DB::table('web_project_managers')->insert(['user_id' => $pm->getKey(), 'account_id' => 77]);
    $program = wtUser(RoleId::ProgramManager, 'Paula');
    $workflow = app(RequestWorkflow::class);

    $workflow->submit($pm, $workflow->create($pm, ['project' => 'F2F', 'type' => 'monthly', 'month' => '2026-11'], [['budget_line' => 'F2F|Maintenance', 'amount' => '300.00', 'currency' => 'USD']]));

    expect(wtWaiting($program))->toMatchArray(['decide' => 1])
        ->and(wtWaiting($pm))->toMatchArray(['my_requests' => 1]);
});

it('puts the panel on the home page with links to the filtered lists, and leaves it out for roles with nothing to do', function () {
    $manager = wtUser(RoleId::FinancialManager, 'Fm');

    // The home page loads the panel right after itself, so the balances never wait for it.
    Livewire::actingAs($manager)->test(Home::class)->assertSeeHtml('data-test="home-waiting-loading"');

    Livewire::withoutLazyLoading()->actingAs($manager)->test(HomeWaiting::class)
        ->assertSeeHtml('data-test="home-waiting"')
        ->assertSeeHtml('data-test="waiting-approve"')
        ->assertSee(route(Navigation::routeName('vouchers'), ['status' => 'pending_approval']), false);

    Livewire::withoutLazyLoading()->actingAs(wtUser(RoleId::Employee, 'Emp'))->test(HomeWaiting::class)->assertDontSeeHtml('data-test="home-waiting"');
});

it('filters the vouchers and requests lists to the person\'s own', function () {
    $ann = wtUser(RoleId::Accountant, 'Ann');
    $ben = wtUser(RoleId::Accountant, 'Ben');
    $head = wtUser(RoleId::HeadOffice, 'Ho');
    $mine = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $ann->getKey()]));
    $theirs = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $ben->getKey()]));

    Livewire::actingAs($ann)->test(Vouchers::class)->set('mine', true)->assertSee($mine->id)->assertDontSee($theirs->id);
    Livewire::actingAs($head)->test(Requests::class)->set('mine', true)->assertSeeHtml('data-test="only-mine"');
});

// U-06

it('finds vouchers, accounts, projects and screens, a full voucher number first', function () {
    $head = wtUser(RoleId::HeadOffice, 'Ho');
    $voucher = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => 5, 'description' => 'Grant for winter']));

    $groups = app(GlobalSearch::class)->search($head, $voucher->id);
    expect($groups['vouchers'][0]['label'])->toBe($voucher->id)
        ->and($groups['vouchers'][0]['url'])->toContain('voucher='.$voucher->id);

    expect(app(GlobalSearch::class)->search($head, 'winter')['vouchers'][0]['label'])->toBe($voucher->id)
        ->and(app(GlobalSearch::class)->search($head, '1111')['accounts'][0]['label'])->toStartWith('1111')
        ->and(collect(app(GlobalSearch::class)->search($head, 'Education')['projects'])->pluck('label')->all())->toBe(['EDU · Education Homs'])
        ->and(collect(app(GlobalSearch::class)->search($head, 'trial')['screens'])->pluck('label')->all())->toContain(__('navigation.screens.tb'))
        ->and(app(GlobalSearch::class)->search($head, 'x'))->toBe([]);
});

it('never shows what the person may not see', function () {
    app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => 5, 'description' => 'Grant for winter']));
    $employee = wtUser(RoleId::Employee, 'Emp');

    expect(app(GlobalSearch::class)->search($employee, 'winter'))->toBe([])
        ->and(app(GlobalSearch::class)->search($employee, 'Education'))->toBe([]);

    // A project manager limited to F2F does not find the Homs project.
    $pm = wtUser(RoleId::ProjectManager, 'Pam');
    DB::connection('legacy')->table('user_accounts')->insert(['user_id' => $pm->getKey(), 'account_id' => 77]);
    $projects = collect(app(GlobalSearch::class)->search($pm, 'E')['projects'] ?? [])->pluck('label')->all();
    expect($projects)->not->toContain('EDU · Education Homs');
});

it('takes LIKE wildcards literally', function () {
    app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => 5, 'description' => 'Grant for winter']));

    expect(app(GlobalSearch::class)->search(wtUser(RoleId::HeadOffice, 'Ho'), '%%'))->not->toHaveKey('vouchers');
});

it('puts the search window in the top bar', function () {
    $head = wtUser(RoleId::HeadOffice, 'Ho');

    $this->actingAs($head)->get('/bridge/decisions')->assertOk()->assertSee('data-test="search-button"', false)->assertSee('data-test="search-dialog"', false);

    Livewire::actingAs($head)->test(SearchDialog::class)->set('term', 'trial')->assertSee(__('navigation.screens.tb'))
        ->set('term', 'zzzzqq')->assertSeeHtml('data-test="search-empty"');
});

// U-07

it('marks the entry screens\' buttons for the shortcuts and lists them', function () {
    $accountant = wtUser(RoleId::Accountant, 'Acc');

    foreach ([SingleEntry::class, MultiEntry::class] as $screen) {
        Livewire::actingAs($accountant)->test($screen)->assertSeeHtml('data-shortcut="save"');
    }

    $this->actingAs(wtUser(RoleId::HeadOffice, 'Ho'))->get(route('entries.vouchers.create'))->assertOk()
        ->assertSee('data-shortcut="save"', false)->assertSee('data-shortcut="add-line"', false)
        ->assertSee('data-test="shortcuts-help"', false)
        ->assertSee('data-test="shortcuts-button"', false);
});

it('opens a fresh form after Ctrl+Enter saved an entry, and says what was saved', function () {
    $accountant = wtUser(RoleId::Accountant, 'Acc');

    Livewire::actingAs($accountant)->test(SingleEntry::class)
        ->set('kind', 'income')->set('date', '2026-03-10')->set('project', 'F2F')->set('cash', '1111')->set('account', '4101')->set('amount', '250')
        ->set('thenNew', true)
        ->call('save')
        ->assertRedirect(route(Navigation::routeName('single')));

    $this->actingAs($accountant)->get(route(Navigation::routeName('single')))->assertOk();
});

it('has the search, shortcut and waiting texts in English and Arabic', function () {
    $keys = fn (string $file, string $locale) => collect(require lang_path("$locale/$file.php"))->dot()->keys()->sort()->values()->all();

    foreach (['search', 'shortcuts'] as $file) {
        expect($keys($file, 'ar'))->toBe($keys($file, 'en'));
    }

    expect(collect(require lang_path('ar/home.php'))->dot()->keys()->filter(fn ($key) => str_starts_with($key, 'waiting.'))->sort()->values()->all())
        ->toBe(collect(require lang_path('en/home.php'))->dot()->keys()->filter(fn ($key) => str_starts_with($key, 'waiting.'))->sort()->values()->all());
});
