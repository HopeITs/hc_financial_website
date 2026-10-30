<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\Operations;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\LineDraft;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (new ChartOfAccountsSeeder)->run();
    Storage::fake('local');

    // The seeder keeps only the chart, so the projects these operations use are made here.
    $project = fn (string $code, bool $general = false) => [
        'code' => $code, 'name' => $code, 'donor' => $general ? null : 'Donor', 'restriction' => $general ? 'Without donor restrictions' : 'With donor restrictions',
        'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => $general, 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('gl_projects')->insert([$project('F2F'), $project('SZ'), $project('GEN-ALP-USD', true), $project('GEN-ALP-SYP', true)]);
    DB::table('gl_budget_lines')->insert([
        'key' => 'F2F|Bank charges', 'project_code' => 'F2F', 'donor' => 'Donor', 'name' => 'Bank charges', 'account_code' => '5501',
        'budget' => 1000, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Aleppo's general USD account holds 5,000 USD, and F2F 1,000 USD, both in the main safe.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 5000, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
        new LineDraft('4101', 'USD', credit: 5000, projectCode: 'GEN-ALP-USD'),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-02'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 1000, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 1000, projectCode: 'F2F'),
    ]);
});

function operator(RoleId $role = RoleId::Accountant, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** The voucher an operation saved: the setup's vouchers are approved, the saved ones wait. */
function pendingVoucher(?string $kind = null): Voucher
{
    return Voucher::query()->where('status', 'pending_approval')->when($kind !== null, fn ($query) => $query->where('transfer_kind', $kind))->sole();
}

/** @return list<array<string, mixed>> */
function savedLines(Voucher $voucher): array
{
    return DB::table('gl_voucher_lines')->where('voucher_id', $voucher->id)->orderBy('line_no')->get()->map(fn ($line) => (array) $line)->all();
}

it('is the Operations screen in the menu, and the old single-entry address leads to it', function () {
    $ann = operator();

    expect(Navigation::path('single'))->toBe('entries/operations');

    $this->actingAs($ann)->get('/entries/single?type=CPV')->assertRedirect('/entries/operations?type=CPV');
    $this->actingAs($ann)->get('/entries/operations')->assertOk()->assertSee('Operations')->assertSee('Allocation');
});

it('offers the accountant every type but the manual entry, which is for the Financial Manager and Head Office', function () {
    Livewire::actingAs(operator())->test(Operations::class)
        ->assertSeeHtml('data-test="type-CPV"')
        ->assertSeeHtml('data-test="type-ALC"')
        ->assertDontSeeHtml('data-test="type-JV"')
        ->call('pick', 'JV')
        ->assertSet('type', '');

    Livewire::actingAs(operator(RoleId::HeadOffice, 'Hadi'))->test(Operations::class)
        ->call('pick', 'JV')
        ->assertSet('type', 'JV')
        ->assertSeeHtml('data-test="open-builder"');
});

it('pays an expense from a budget line: the line fills the project and the account, and the lines are shown before saving', function () {
    $test = Livewire::actingAs(operator())->test(Operations::class)
        ->call('pick', 'CPV')
        ->assertSet('mode', 'expense')
        ->set('date', '2026-03-10')
        ->set('budgetLine', 'F2F|Bank charges')
        ->assertSet('project', 'F2F')
        ->assertSet('account', '5501')
        ->set('cash', '1111')
        ->set('amount', '120')
        ->set('contact', 'Bank')
        ->assertSeeHtml('data-test="preview-lines"')
        ->assertSeeHtml('data-test="ready"');

    expect(Voucher::query()->count())->toBe(2);

    $test->call('save')->assertSet('failures', []);

    $voucher = pendingVoucher();
    $lines = savedLines($voucher);

    expect($voucher->type)->toBe(VoucherType::Payment)
        ->and($voucher->status->value)->toBe('pending_approval')
        ->and(array_column($lines, 'account_code'))->toBe(['5501', '1111'])
        ->and((float) $lines[0]['debit_usd'])->toBe(120.0)
        ->and($lines[0]['budget_line_key'])->toBe('F2F|Bank charges')
        ->and((float) $lines[1]['credit_usd'])->toBe(120.0);
});

it('receives a grant into a till', function () {
    Livewire::actingAs(operator())->test(Operations::class)
        ->call('pick', 'CRV')
        ->set('date', '2026-03-10')->set('project', 'SZ')->set('account', '4101')->set('cash', '1111')->set('amount', '300')
        ->call('save')
        ->assertSet('failures', []);

    $voucher = pendingVoucher();

    expect($voucher->type)->toBe(VoucherType::Receipt)
        ->and(array_column(savedLines($voucher), 'account_code'))->toBe(['1111', '4101']);
});

it('allocates dollars from the city general account to a project, and exchanges dollars for pounds', function () {
    Livewire::actingAs(operator())->test(Operations::class)
        ->call('pick', 'ALC')
        ->assertSet('mode', 'usd_to_project')
        ->assertSet('cash', '1111')
        ->set('date', '2026-04-01')->set('project', 'SZ')->set('amount', '400')
        ->call('save')
        ->assertSet('failures', []);

    $allocation = pendingVoucher('usd_to_project');
    expect($allocation->type)->toBe(VoucherType::Transfer)
        ->and($allocation->transfer_kind)->toBe('usd_to_project')
        ->and(array_column(savedLines($allocation), 'project_code'))->toBe(['SZ', 'GEN-ALP-USD']);

    Livewire::actingAs(operator())->test(Operations::class)
        ->call('pick', 'EXG')
        ->assertSet('sypCash', '1112')
        ->set('date', '2026-04-02')->set('amount', '100')->set('rate', '15000')
        ->call('save')
        ->assertSet('failures', []);

    $exchange = pendingVoucher('local_exchange');
    expect($exchange->transfer_kind)->toBe('local_exchange')
        ->and(array_column(savedLines($exchange), 'account_code'))->toBe(['1111', '1112']);
});

it('transfers between projects, and explains instead of saving when the project has too little', function () {
    $test = Livewire::actingAs(operator())->test(Operations::class)
        ->call('pick', 'TRF')
        ->assertSet('mode', 'between_projects')
        ->set('date', '2026-04-01')->set('project', 'F2F')->set('otherProject', 'SZ')->set('amount', '5000')
        ->assertSeeHtml('data-test="operation-messages"')
        ->call('save');

    expect($test->get('failures'))->not->toBe([])
        ->and(Voucher::query()->count())->toBe(2);

    $test->set('amount', '250')->call('save')->assertSet('failures', []);

    $voucher = pendingVoucher();
    expect($voucher->transfer_kind)->toBe('between_projects')
        ->and(array_column(savedLines($voucher), 'project_code'))->toBe(['SZ', 'F2F']);
});

it('opens on the type in the address, and after Ctrl+Enter comes back to a fresh form of the same type', function () {
    Livewire::withQueryParams(['type' => 'CRV'])->actingAs(operator())->test(Operations::class)
        ->assertSet('type', 'CRV')
        ->assertSet('mode', 'income')
        ->set('date', '2026-03-10')->set('project', 'SZ')->set('account', '4101')->set('cash', '1111')->set('amount', '10')
        ->set('thenNew', true)
        ->call('save')
        ->assertRedirect(route(Navigation::routeName('single'), ['type' => 'CRV']));
});
