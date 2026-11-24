<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\DonorFinancialReport;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\DonorFinancialReports;
use App\Services\Ledger\LineDraft;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // A euro donor's project with a euro budget line and a dollar one (as in DonorReportTest).
    DB::table('gl_projects')->insert([
        'code' => 'F2F', 'name' => 'Family to Family', 'donor' => 'Caritas', 'restriction' => 'With donor restrictions', 'city' => 'Aleppo',
        'donor_currency' => 'EUR', 'donor_rate' => 1.15, 'is_general_account' => false, 'contract_number' => 'CP-7',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('gl_budget_lines')->insert([
        ['key' => 'F2F|Bank charges', 'project_code' => 'F2F', 'donor' => 'Caritas', 'name' => 'Bank charges', 'account_code' => '5501', 'budget' => 1000, 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'F2F|Training', 'project_code' => 'F2F', 'donor' => 'Caritas', 'name' => 'Training', 'account_code' => '5501', 'budget' => 400, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // February: the donor sent 1,000 EUR (1,150 USD). March: 200 EUR (230 USD) on bank charges, 100 USD on training.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-02-01'), 'type' => VoucherType::Receipt, 'description' => 'First tranche'], [
        new LineDraft('1161', 'USD', debit: 1150, projectCode: 'F2F', fundSource: FundSource::DonorGrant, contact: 'Hawala office', donorCurrency: 'EUR', debitDonor: 1000, eurAmount: 1000, eurRate: 1.15),
        new LineDraft('4001', 'USD', credit: 1150, projectCode: 'F2F', donorCurrency: 'EUR', creditDonor: 1000),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Payment, 'description' => 'Bank fees'], [
        new LineDraft('5501', 'USD', debit: 230, projectCode: 'F2F', budgetLineKey: 'F2F|Bank charges', donorCurrency: 'EUR', debitDonor: 200),
        new LineDraft('1111', 'USD', credit: 230, projectCode: 'F2F', fundSource: FundSource::DonorGrant, donorCurrency: 'EUR', creditDonor: 200),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-02'), 'type' => VoucherType::Payment, 'description' => 'Workshop'], [
        new LineDraft('5501', 'USD', debit: 100, projectCode: 'F2F', budgetLineKey: 'F2F|Training', donorCurrency: 'EUR', debitDonor: 86.96),
        new LineDraft('1111', 'USD', credit: 100, projectCode: 'F2F', fundSource: FundSource::DonorGrant, donorCurrency: 'EUR', creditDonor: 86.96),
    ]);
});

function dfProject(): GlProject
{
    return GlProject::query()->findOrFail('F2F');
}

it('sets each budget line\'s spending before, in and to the end of the period against its budget', function () {
    $report = app(DonorFinancialReports::class)->budgetVsActual(dfProject(), CarbonImmutable::parse('2026-03-02'), CarbonImmutable::parse('2026-03-31'));

    expect(collect($report['lines'])->map(fn ($line) => [$line['key'], $line['currency'], $line['budget'], $line['before'], $line['period'], $line['to_date'], $line['remaining'], $line['percent'], $line['period_usd']])->all())
        ->toBe([
            ['F2F|Bank charges', 'EUR', '1000.00', '200.00', '0.00', '200.00', '800.00', '20.0', '0.00'],
            ['F2F|Training', 'USD', '400.00', '0.00', '100.00', '100.00', '300.00', '25.0', '100.00'],
        ])
        ->and($report['period_usd'])->toBe('100.00');
});

it('lists the period\'s expenses line by line, and the donor\'s money from the start of the period to its end', function () {
    $reports = app(DonorFinancialReports::class);
    $march = [CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31')];

    $expenses = $reports->expenses(dfProject(), ...$march);
    expect(array_column($expenses['rows'], 'description'))->toBe(['Bank fees', 'Workshop'])
        ->and($expenses['rows'][0])->toMatchArray(['line' => 'Bank charges', 'amount' => '230.00', 'usd' => '230.00', 'attachments' => 0])
        ->and($expenses['usd'])->toBe('330.00');

    $funds = $reports->funds(dfProject(), ...$march);
    expect($funds['opening'])->toBe(['donor' => '1000.00', 'usd' => '1150.00'])
        ->and($funds['received'])->toBe(['donor' => '0.00', 'usd' => '0.00'])
        ->and($funds['spent'])->toBe(['donor' => '286.96', 'usd' => '330.00'])
        ->and($funds['closing'])->toBe(['donor' => '713.04', 'usd' => '820.00'])
        ->and($reports->funds(dfProject(), CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-02-28'))['receipts'])->toHaveCount(1);
});

it('opens in the reports, picks a period and exports all three reports as Excel and PDF', function () {
    $accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));

    $this->actingAs($accountant)->get(route(Navigation::routeName('rdonorfin'), ['project' => 'F2F', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()->assertSeeHtml('data-test="df-bva"')->assertSee('Bank charges');

    Livewire::actingAs($accountant)->withQueryParams(['project' => 'F2F'])->test(DonorFinancialReport::class)
        ->assertSet('from', '2026-01-01')
        ->set('tab', 'expenses')->assertSeeHtml('data-test="df-expenses"')->assertSee('Workshop')
        ->set('tab', 'funds')->assertSeeHtml('data-test="df-funds"')
        ->set('from', '2026-04-01')->set('to', '2026-03-01')->assertSeeHtml('data-test="bad-period"')
        ->call('preset', 'project')
        ->call('export', 'xlsx')->assertFileDownloaded()
        ->call('export', 'pdf')->assertFileDownloaded();

    $this->actingAs(User::query()->findOrFail(createFinanceUser('Carl', 'secret pass', RoleId::Cashier->name, RoleId::Cashier->value)))
        ->get(route(Navigation::routeName('rdonorfin')))->assertForbidden();
});
