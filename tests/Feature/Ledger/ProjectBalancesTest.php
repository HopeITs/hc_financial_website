<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\ProjectBalances;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\ProjectBalances as ProjectBalancesReport;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function approvedVoucher(array $lines, bool $approve = true): void
{
    $voucher = app(PostVoucher::class)->post(ledgerReceipt([], $lines));

    if ($approve) {
        DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);
    }
}

function receiveFor(string $project, int $amount, bool $approve = true): void
{
    approvedVoucher([
        new LineDraft('1111', 'USD', debit: $amount, projectCode: $project, fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: $project),
    ], $approve);
}

it('sums income, expenses and cash per project from approved lines only', function () {
    receiveFor('F2F', 100);
    receiveFor('F2F', 999, approve: false);
    receiveFor('SZ', 30);

    $rows = collect(app(ProjectBalancesReport::class)->report()['rows'])->keyBy('code');

    expect($rows['F2F']['income'])->toBe('100.00')
        ->and($rows['F2F']['cash'])->toBe('100.00')
        ->and($rows['F2F']['expenses'])->toBe('0.00')
        ->and($rows['F2F']['restricted'])->toBe('100.00')
        ->and($rows['SZ']['income'])->toBe('30.00');
});

it('leaves 5703 depreciation out of expenses', function () {
    receiveFor('F2F', 100);
    approvedVoucher([
        new LineDraft('5703', 'USD', debit: 15, projectCode: 'F2F'),
        new LineDraft('1111', 'USD', credit: 15, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);

    $row = collect(app(ProjectBalancesReport::class)->report()['rows'])->keyBy('code')['F2F'];

    expect($row['expenses'])->toBe('0.00')->and($row['cash'])->toBe('85.00');
});

it('keeps the restricted fund at zero for a project without donor restrictions', function () {
    receiveFor('CORE', 50);

    $row = collect(app(ProjectBalancesReport::class)->report()['rows'])->keyBy('code')['CORE'];

    expect($row['income'])->toBe('50.00')->and($row['restricted'])->toBe('0.00');
});

it('lists the cash of a project per cash account and currency without mixing them', function () {
    receiveFor('F2F', 100);
    approvedVoucher([
        new LineDraft('1112', 'SYP', debit: 1000000, rate: '10000', debitUsd: 100, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 100, projectCode: 'F2F'),
    ]);

    $locations = collect(app(ProjectBalancesReport::class)->fundLocations('F2F'))->keyBy('account_code');

    expect($locations)->toHaveCount(2)
        ->and($locations['1111']['currency'])->toBe('USD')
        ->and($locations['1111']['amount'])->toBe('100.00')
        ->and($locations['1112']['currency'])->toBe('SYP')
        ->and($locations['1112']['amount'])->toBe('1000000.00')
        ->and($locations['1112']['usd'])->toBe('100.00');
});

it('compares each budget line with its approved spending', function () {
    $line = GlBudgetLine::query()->where('project_code', 'SZ')->where('currency', 'USD')->first();
    expect($line)->not->toBeNull();

    receiveFor('SZ', 200);
    approvedVoucher([
        new LineDraft($line->account_code, 'USD', debit: 40, projectCode: 'SZ', budgetLineKey: $line->key, center: 'Aleppo'),
        new LineDraft('1111', 'USD', credit: 40, projectCode: 'SZ', fundSource: FundSource::DonorGrant),
    ]);

    $row = collect(app(ProjectBalancesReport::class)->budgetVsActual('SZ'))->keyBy('key')[$line->key];

    expect($row['actual'])->toBe('40.00')
        ->and($row['remaining'])->toBe(bcsub((string) $line->budget, '40', 2));
});

it('shows SYP lines as entered and converts USD lines at the typed rate', function () {
    receiveFor('F2F', 100);
    approvedVoucher([
        new LineDraft('1112', 'SYP', debit: 500000, rate: '10000', debitUsd: 50, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 50, projectCode: 'F2F'),
    ]);

    $row = collect(app(ProjectBalancesReport::class)->report('SYP', '12000')['rows'])->keyBy('code')['F2F'];

    expect($row['cash'])->toBe('1700000.00')
        ->and($row['income'])->toBe('1800000.00');
});

it('renders for the financial manager and refuses a cashier', function () {
    receiveFor('F2F', 100);

    $manager = User::query()->findOrFail(createFinanceUser('fm', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value));
    $this->actingAs($manager);
    Livewire::test(ProjectBalances::class)->assertSee('F2F')->assertSee('100.00');
    $this->get(route('reports.project-balances'))->assertOk();

    $cashier = User::query()->findOrFail(createFinanceUser('cash', 'secret pass', RoleId::Cashier->name, RoleId::Cashier->value));
    $this->actingAs($cashier)->get(route('reports.project-balances'))->assertForbidden();
});
