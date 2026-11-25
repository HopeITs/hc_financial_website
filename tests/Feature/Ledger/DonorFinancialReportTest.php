<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\DonorFinancialReport;
use App\Models\BudgetLineNote;
use App\Models\GlProject;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\DonorFinancialReports;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\VoucherDocuments;
use App\Services\Treasury\MonthlyRates;
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
    (require database_path('migrations/2026_11_26_090000_add_donor_report_details.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // A euro donor's project (1.15 USD per EUR) with a euro budget line and a dollar one, in two categories.
    DB::table('gl_projects')->insert([
        'code' => 'F2F', 'name' => 'Family to Family', 'donor' => 'Caritas', 'restriction' => 'With donor restrictions', 'city' => 'Aleppo',
        'donor_currency' => 'EUR', 'donor_rate' => 1.15, 'is_general_account' => false, 'contract_number' => 'CP-7',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('gl_budget_lines')->insert([
        ['key' => 'F2F|Bank charges', 'project_code' => 'F2F', 'donor' => 'Caritas', 'name' => 'Bank charges', 'category' => 'Operations', 'account_code' => '5501', 'budget' => 1000, 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'F2F|Training', 'project_code' => 'F2F', 'donor' => 'Caritas', 'name' => 'Training', 'category' => 'Activities', 'account_code' => '5501', 'budget' => 460, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // February: the donor sent 1,000 EUR (1,150 USD). March: 200 EUR (230 USD) on bank charges, 100 USD on training.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-02-01'), 'type' => VoucherType::Receipt, 'description' => 'First tranche'], [
        new LineDraft('1161', 'USD', debit: 1150, projectCode: 'F2F', fundSource: FundSource::DonorGrant, contact: 'Hawala office', donorCurrency: 'EUR', debitDonor: 1000, eurAmount: 1000, eurRate: 1.15),
        new LineDraft('4001', 'USD', credit: 1150, projectCode: 'F2F', donorCurrency: 'EUR', creditDonor: 1000),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Payment, 'description' => 'رسوم البنك'], [
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

function dfMarch(): array
{
    return [CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31')];
}

it('gives the project\'s page and the summary by budget category in the donor\'s currency', function () {
    $reports = app(DonorFinancialReports::class);

    // The dollar line's 460 USD is 400 EUR at the project's 1.15.
    expect($reports->info(dfProject(), ...dfMarch()))->toMatchArray([
        'currency' => 'EUR', 'budget' => '1400.00', 'received' => ['donor' => '1000.00', 'usd' => '1150.00'],
        'spent' => ['donor' => '286.96', 'usd' => '330.00'], 'balance' => ['donor' => '713.04', 'usd' => '820.00'], 'percent' => '20.4',
    ]);

    $summary = $reports->categories(dfProject(), ...dfMarch());
    expect(collect($summary['rows'])->map(fn ($row) => [$row['category'], $row['budget'], $row['to_date'], $row['percent']])->all())
        ->toBe([['Operations', '1000.00', '200.00', '20.0'], ['Activities', '400.00', '86.96', '21.7']])
        ->and($summary['total'])->toMatchArray(['budget' => '1400.00', 'to_date' => '286.96']);
});

it('lists the expenses with their document, the English description, each currency and rate, and the totals', function () {
    app(MonthlyRates::class)->set('2026-03', 'SYP', '13000', null, User::query()->findOrFail(createFinanceUser('Fm', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value)));
    $workshop = Voucher::query()->where('description', 'Workshop')->firstOrFail();
    app(VoucherDocuments::class)->record($workshop, 'invoice', 'INV-77', 'Training workshop for 20 women');
    $bank = Voucher::query()->where('description', 'رسوم البنك')->firstOrFail();
    app(VoucherDocuments::class)->record($bank, 'receipt', 'B-1', null);

    $expenses = app(DonorFinancialReports::class)->expenses(dfProject(), ...dfMarch());

    expect($expenses['donor_currency'])->toBe('EUR')
        ->and($expenses['rows'][1])->toMatchArray([
            'document_type' => 'invoice', 'document_number' => 'INV-77', 'description' => 'Training workshop for 20 women',
            'line' => 'Training', 'category' => 'Activities', 'usd' => '100.00', 'donor' => '86.96', 'syp' => '1300000.00', 'syp_rate' => '13000.000000',
        ])
        // Without an English description the voucher's own stands.
        ->and($expenses['rows'][0]['description'])->toBe('رسوم البنك')
        ->and($expenses['totals'])->toBe(['syp' => '4290000.00', 'usd' => '330.00', 'donor' => '286.96']);
});

it('sets each line against its budget for the period, with the reason written for that period', function () {
    $reports = app(DonorFinancialReports::class);
    [$from, $to] = dfMarch();
    $reports->saveNote(\App\Models\GlBudgetLine::query()->findOrFail('F2F|Training'), $from, $to, 'Fewer sessions than planned', 1);

    $lines = collect($reports->budgetVsActual(dfProject(), $from, $to)['lines'])->keyBy('key');

    expect($lines['F2F|Training'])->toMatchArray(['category' => 'Activities', 'budget' => '460.00', 'period' => '100.00', 'remaining' => '360.00', 'percent' => '21.7', 'status' => 'under', 'note' => 'Fewer sessions than planned'])
        ->and($lines['F2F|Bank charges'])->toMatchArray(['period' => '200.00', 'note' => ''])
        // A note belongs to its period only.
        ->and(collect($reports->budgetVsActual(dfProject(), CarbonImmutable::parse('2026-01-01'), $to)['lines'])->keyBy('key')['F2F|Training']['note'])->toBe('');

    $reports->saveNote(\App\Models\GlBudgetLine::query()->findOrFail('F2F|Training'), $from, $to, '', 1);
    expect(BudgetLineNote::query()->count())->toBe(0);
});

it('opens in the reports, writes a reason from the page, and exports every page as Excel and PDF', function () {
    $accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));

    $this->actingAs($accountant)->get(route(Navigation::routeName('rdonorfin'), ['project' => 'F2F', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()->assertSeeHtml('data-test="df-info"')->assertSee('Family to Family');

    Livewire::actingAs($accountant)->withQueryParams(['project' => 'F2F', 'from' => '2026-03-01', 'to' => '2026-03-31'])->test(DonorFinancialReport::class)
        ->set('tab', 'summary')->assertSeeHtml('data-test="df-summary"')->assertSee('Operations')
        ->set('tab', 'expenses')->assertSeeHtml('data-test="expense-totals"')->assertSee('Workshop')
        ->set('tab', 'bva')->assertSeeHtml('data-test="df-bva"')
        ->call('saveNote', 'F2F|Training', 'Fewer sessions')
        ->assertSee(__('donor_financial.note_saved', ['line' => 'Training']))
        ->set('tab', 'funds')->assertSeeHtml('data-test="df-funds"')
        ->call('export', 'xlsx')->assertFileDownloaded()
        ->call('export', 'pdf')->assertFileDownloaded();

    expect(BudgetLineNote::query()->value('note'))->toBe('Fewer sessions');

    // The General Manager reads the report but writes no reason; the cashier does not open it.
    $general = User::query()->findOrFail(createFinanceUser('Gabriel', 'secret pass', RoleId::Employee->name, RoleId::Employee->value));
    DB::table('web_user_roles')->insert(['user_id' => $general->getKey(), 'role_id' => RoleId::GeneralManager->value]);
    Livewire::actingAs($general)->withQueryParams(['project' => 'F2F'])->test(DonorFinancialReport::class)
        ->call('saveNote', 'F2F|Training', 'x')->assertForbidden();

    $this->actingAs(User::query()->findOrFail(createFinanceUser('Carl', 'secret pass', RoleId::Cashier->name, RoleId::Cashier->value)))
        ->get(route(Navigation::routeName('rdonorfin')))->assertForbidden();
});

it('keeps a voucher\'s document details, changed by hand in its audit', function () {
    $workshop = Voucher::query()->where('description', 'Workshop')->firstOrFail();
    $accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));

    expect(app(VoucherDocuments::class)->change($workshop, $accountant, 'invoice', 'INV-9', 'Workshop'))->toBe([])
        ->and($workshop->fresh()->document_number)->toBe('INV-9')
        ->and(DB::table('gl_voucher_audit')->where('voucher_id', $workshop->id)->where('action', 'document')->count())->toBe(3)
        ->and(app(VoucherDocuments::class)->change($workshop, $accountant, 'bill', '', ''))->toBe([__('voucher_documents.bad_type')]);
});

it('makes the monthly report in dollars: by category and line, with each line\'s status and the months\' breakdown', function () {
    $monthly = app(App\Services\Ledger\MonthlyProjectReport::class);
    $march = CarbonImmutable::parse('2026-03-01');
    $report = $monthly->build(dfProject(), $march);

    // The euro line's 1,000 EUR is 1,150 USD at the project's 1.15; March is month 3 of 12.
    expect([$report['months_elapsed'], $report['months_total'], $report['time']])->toBe([3, 12, '0.2500'])
        ->and($report['totals'])->toMatchArray(['budget' => '1610.00', 'previous' => '0.00', 'month' => '330.00', 'to_date' => '330.00', 'remaining' => '1280.00'])
        ->and(collect($report['lines'])->map(fn ($line) => [$line['name'], $line['budget'], $line['month'], $line['burn'], $line['status']])->all())->toBe([
            ['Bank charges', '1150.00', '230.00', '0.2000', 'ok'],
            ['Training', '460.00', '100.00', '0.2173', 'ok'],
        ])
        ->and(collect($report['categories'])->pluck('category')->all())->toBe(['Operations', 'Activities']);

    // In December the same spending is far behind the time gone.
    expect(collect($monthly->build(dfProject(), CarbonImmutable::parse('2026-12-01'))['lines'])->pluck('status')->all())->toBe(['slow', 'slow']);

    $breakdown = $monthly->breakdown(dfProject(), $march);
    expect($breakdown['months'])->toBe(['2026-01', '2026-02', '2026-03'])
        ->and($breakdown['rows'][0]['by_month'])->toBe(['2026-01' => '0.00', '2026-02' => '0.00', '2026-03' => '230.00'])
        ->and($breakdown['totals']['2026-03'])->toBe('330.00');

    // Spending over the line's budget asks for an explanation.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-20'), 'type' => VoucherType::Payment, 'description' => 'Second workshop'], [
        new LineDraft('5501', 'USD', debit: 400, projectCode: 'F2F', budgetLineKey: 'F2F|Training'),
        new LineDraft('1111', 'USD', credit: 400, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);
    $over = $monthly->build(dfProject(), $march);
    expect($over['lines'][1]['status'])->toBe('over')->and($over['to_explain'])->toBe(1);
});

it('shows the monthly report, keeps the month\'s explanation and exports it', function () {
    $accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));

    $page = Livewire::actingAs($accountant)->withQueryParams(['project' => 'F2F', 'from' => '2026-01-01', 'to' => '2026-03-31', 'tab' => 'monthly'])
        ->test(DonorFinancialReport::class)
        ->assertSet('month', '2026-03')
        ->assertSeeHtml('data-test="df-monthly-categories"')
        ->assertSeeHtml('data-test="df-monthly-lines"')
        ->assertSeeHtml('data-test="df-monthly-breakdown"');

    $page->call('saveMonthlyNote', 'F2F|Training', 'Two workshops in March');
    expect(BudgetLineNote::query()->where('budget_line_key', 'F2F|Training')->whereDate('period_from', '2026-03-01')->whereDate('period_to', '2026-03-31')->value('note'))->toBe('Two workshops in March');

    $page->call('exportMonthly', 'xlsx')->assertFileDownloaded('HC_F2F_FinReport_2026-03.xlsx');
});
