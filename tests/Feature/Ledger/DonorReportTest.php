<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\DonorReportScreen;
use App\Models\User;
use App\Services\Ledger\DonorReport;
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

    // A euro donor's project with a euro budget line and a dollar one.
    DB::table('gl_projects')->insert([
        'code' => 'F2F', 'name' => 'Family to Family', 'donor' => 'Caritas', 'restriction' => 'With donor restrictions', 'city' => 'Aleppo',
        'donor_currency' => 'EUR', 'donor_rate' => 1.15, 'is_general_account' => false, 'contract_number' => 'CP-7',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('gl_budget_lines')->insert([
        ['key' => 'F2F|Bank charges', 'project_code' => 'F2F', 'donor' => 'Caritas', 'name' => 'Bank charges', 'account_code' => '5501', 'budget' => 1000, 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'F2F|Training', 'project_code' => 'F2F', 'donor' => 'Caritas', 'name' => 'Training', 'account_code' => '5501', 'budget' => 400, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // The donor sent 1,000 EUR through the transfer company, which became 1,150 USD; 200 EUR (230 USD) went on bank charges and 100 USD on training.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-02-01'), 'type' => VoucherType::Receipt, 'description' => 'First tranche'], [
        new LineDraft('1161', 'USD', debit: 1150, projectCode: 'F2F', fundSource: FundSource::DonorGrant, contact: 'Hawala office', donorCurrency: 'EUR', debitDonor: 1000, eurAmount: 1000, eurRate: 1.15),
        new LineDraft('4101', 'USD', credit: 1150, projectCode: 'F2F', donorCurrency: 'EUR', creditDonor: 1000),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Payment], [
        new LineDraft('5501', 'USD', debit: 230, projectCode: 'F2F', budgetLineKey: 'F2F|Bank charges', donorCurrency: 'EUR', debitDonor: 200),
        new LineDraft('1111', 'USD', credit: 230, projectCode: 'F2F', fundSource: FundSource::DonorGrant, donorCurrency: 'EUR', creditDonor: 200),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-02'), 'type' => VoucherType::Payment], [
        new LineDraft('5501', 'USD', debit: 100, projectCode: 'F2F', budgetLineKey: 'F2F|Training', donorCurrency: 'EUR', debitDonor: 86.96),
        new LineDraft('1111', 'USD', credit: 100, projectCode: 'F2F', fundSource: FundSource::DonorGrant, donorCurrency: 'EUR', creditDonor: 86.96),
    ]);
});

it('sets each budget line against its spending in its own currency, with the dollars beside it', function () {
    $report = app(DonorReport::class)->of('F2F');

    expect(collect($report['lines'])->map(fn ($line) => [$line['key'], $line['currency'], $line['budget'], $line['actual'], $line['remaining'], $line['percent'], $line['usd']])->all())
        ->toBe([
            ['F2F|Bank charges', 'EUR', '1000.00', '200.00', '800.00', '20.0', '230.00'],
            ['F2F|Training', 'USD', '400.00', '100.00', '300.00', '25.0', '100.00'],
        ])
        ->and($report['totals'])->toBe([
            'EUR' => ['budget' => '1000.00', 'actual' => '200.00', 'remaining' => '800.00', 'percent' => '20.0'],
            'USD' => ['budget' => '400.00', 'actual' => '100.00', 'remaining' => '300.00', 'percent' => '25.0'],
        ])
        ->and($report['spent_usd'])->toBe('330.00');
});

it('shows what the donor sent in its currency, the real rate, and what is not spent yet', function () {
    $report = app(DonorReport::class)->of('F2F');

    expect($report['receipts'])->toHaveCount(1)
        ->and($report['receipts'][0])->toMatchArray(['voucher' => 'CRV-2026-0001', 'description' => 'First tranche', 'donor' => '1000.00', 'usd' => '1150.00', 'eur' => '1000.00'])
        ->and($report['received'])->toBe(['donor' => '1000.00', 'usd' => '1150.00'])
        ->and($report['rate'])->toBe('1.150000')
        ->and($report['balance'])->toBe(['donor' => '713.04', 'usd' => '820.00'])
        ->and($report['after_end'])->toBeFalse()
        ->and(app(DonorReport::class)->of('NOPE'))->toBeNull();
});

it('opens on the reports menu, and exports the report as XLSX and PDF', function () {
    $accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));

    $this->actingAs($accountant)->get(route(Navigation::routeName('rdonor'), ['project' => 'F2F']))
        ->assertOk()->assertSeeHtml('data-test="donor-lines"')->assertSee('Bank charges')->assertSee('1,150.00');

    Livewire::actingAs($accountant)->test(DonorReportScreen::class)
        ->assertSet('project', 'F2F')
        ->call('export', 'xlsx')->assertFileDownloaded('donor-report-F2F.xlsx')
        ->call('export', 'pdf')->assertFileDownloaded('donor-report-F2F.pdf');
});

it('says when the funding period has ended', function () {
    DB::table('gl_projects')->where('code', 'F2F')->update(['end_date' => '2026-01-31']);

    expect(app(DonorReport::class)->of('F2F')['after_end'])->toBeTrue();
});
