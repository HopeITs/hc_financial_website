<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Livewire\MicroLoans as MicroLoansScreen;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\MicroLoan;
use App\Models\User;
use App\Services\Ledger\FollowUpFilters;
use App\Services\Ledger\FollowUpReports;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroLoans;
use App\Services\Loans\ScheduleGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    GlBudgetLine::query()->create(['key' => 'F2F|Grants', 'project_code' => 'F2F', 'name' => 'Grants', 'account_code' => '5101', 'budget' => 100000, 'currency' => 'USD']);
    GlBudgetLine::query()->create(['key' => 'SZ|Grants', 'project_code' => 'SZ', 'name' => 'Grants', 'account_code' => '5101', 'budget' => 100000, 'currency' => 'USD']);
});

function approvedLoanRequest(string $status = 'approved'): LedgerRequest
{
    return LedgerRequest::query()->create([
        'type' => 'purchase', 'project_code' => 'F2F', 'amount' => '5000.00', 'currency' => 'USD', 'status' => $status, 'requested_by' => 3,
    ]);
}

function openLoan(array $with = []): array
{
    $a = $with + [
        'beneficiary' => 'Huda', 'project' => 'F2F', 'loan' => '1000', 'grant' => '200', 'currency' => 'USD', 'rate' => '',
        'count' => 4, 'cash' => '1111', 'budget_line' => 'F2F|Grants', 'fund' => FundSource::DonorGrant,
    ];

    return app(MicroLoans::class)->open(
        $a['beneficiary'], $a['project'], $a['loan'], $a['grant'], $a['currency'], $a['rate'], CarbonImmutable::parse('2026-03-01'),
        $a['count'], CarbonImmutable::parse('2026-04-30'), $a['cash'], $a['budget_line'], $a['fund'], 5, approvedLoanRequest()->id,
    );
}

function payLoan(MicroLoan $loan, string $amount, string $cash = '1111', string $rate = '')
{
    return app(MicroLoans::class)->receive($loan, CarbonImmutable::parse('2026-05-10'), $amount, $rate, $cash, 5);
}

it('cuts equal instalments to the cent and gives the rest to the last one', function () {
    $rows = app(ScheduleGenerator::class)->equalMonthly('100.00', 3, CarbonImmutable::parse('2026-01-31'), 'USD');

    expect(array_column($rows, 'amount'))->toBe(['33.33', '33.33', '33.34'])
        ->and(array_column($rows, 'due_date'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('rounds pound instalments to whole pounds and still adds up to the loan', function () {
    $rows = app(ScheduleGenerator::class)->equalMonthly('1000000', 3, CarbonImmutable::parse('2026-01-15'), 'SYP');

    expect(array_column($rows, 'amount'))->toBe(['333333.00', '333333.00', '333334.00']);
    expect(app(ScheduleGenerator::class)->equalMonthly('10.00', 4, CarbonImmutable::parse('2026-01-15'), 'USD'))->toHaveCount(4);
});

it('refuses a schedule without an amount or with a silly count', function () {
    $generator = app(ScheduleGenerator::class);
    $day = CarbonImmutable::parse('2026-01-15');

    expect(fn () => $generator->equalMonthly('0', 3, $day, 'USD'))->toThrow(LoanException::class)
        ->and(fn () => $generator->equalMonthly('10', 0, $day, 'USD'))->toThrow(LoanException::class)
        ->and(fn () => $generator->equalMonthly('10', 121, $day, 'USD'))->toThrow(LoanException::class);
});

it('posts the loan part to 1204, the grant part to the budget line and the cash out for both', function () {
    ['loan' => $loan, 'voucher' => $voucher] = openLoan();

    expect($voucher->type->value)->toBe('CPV')
        ->and($voucher->lines)->toHaveCount(3)
        ->and($voucher->lines[0]->account_code)->toBe('1204')
        ->and($voucher->lines[0]->debit_usd)->toBe('1000.00')
        ->and($voucher->lines[0]->contact)->toBe('Huda')
        ->and($voucher->lines[1]->account_code)->toBe('5101')
        ->and($voucher->lines[1]->debit_usd)->toBe('200.00')
        ->and($voucher->lines[1]->budget_line_key)->toBe('F2F|Grants')
        ->and($voucher->lines[2]->account_code)->toBe('1111')
        ->and($voucher->lines[2]->credit_usd)->toBe('1200.00')
        ->and($loan->instalments)->toHaveCount(4)
        ->and($loan->instalments->pluck('amount')->all())->toBe(['250.00', '250.00', '250.00', '250.00']);
});

it('opens a loan without a grant or a grant without a loan', function () {
    ['voucher' => $loanOnly, 'loan' => $a] = openLoan(['grant' => '0', 'budget_line' => '']);
    ['voucher' => $grantOnly, 'loan' => $b] = openLoan(['loan' => '', 'beneficiary' => 'Omar']);

    expect($loanOnly->lines)->toHaveCount(2)
        ->and($a->instalments)->toHaveCount(4)
        ->and($grantOnly->lines->pluck('account_code')->all())->toBe(['5101', '1111'])
        ->and($b->instalments)->toHaveCount(0);
});

it('posts a pound loan at the given rate and keeps the dollars balanced', function () {
    ['voucher' => $voucher] = openLoan(['currency' => 'SYP', 'rate' => '15000', 'loan' => '15000000', 'grant' => '3000000', 'cash' => '1112']);

    expect($voucher->lines[0]->debit_usd)->toBe('1000.00')
        ->and($voucher->lines[1]->debit_usd)->toBe('200.00')
        ->and($voucher->lines[2]->credit_usd)->toBe('1200.00')
        ->and($voucher->lines[2]->currency)->toBe('SYP');
});

it('refuses a loan with a cash account in the wrong currency, no rate, a general project or a foreign budget line', function () {
    expect(fn () => openLoan(['currency' => 'SYP', 'rate' => '15000', 'cash' => '1111']))->toThrow(LoanException::class)
        ->and(fn () => openLoan(['currency' => 'SYP', 'rate' => '', 'cash' => '1112']))->toThrow(LoanException::class)
        ->and(fn () => openLoan(['project' => 'GEN-ALP-USD']))->toThrow(LoanException::class)
        ->and(fn () => openLoan(['budget_line' => 'SZ|Grants']))->toThrow(LoanException::class)
        ->and(fn () => openLoan(['loan' => '', 'grant' => '']))->toThrow(LoanException::class)
        ->and(fn () => openLoan(['beneficiary' => ' ']))->toThrow(LoanException::class);

    expect(MicroLoan::query()->count())->toBe(0)->and(DB::table('gl_vouchers')->count())->toBe(0);
});

it('leaves no loan behind when a posting rule refuses the voucher', function () {
    DB::table('gl_month_closes')->insert(['month' => '2026-03', 'closed_by' => 9, 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => openLoan())->toThrow(LedgerRuleException::class);
    expect(MicroLoan::query()->count())->toBe(0)->and(DB::table('gl_micro_instalments')->count())->toBe(0);
});

it('receives an instalment as a receipt that credits 1204 and carries recovered loans, the donor and its restriction', function () {
    ['loan' => $loan] = openLoan();
    $project = GlProject::query()->findOrFail('F2F');

    $voucher = payLoan($loan, '250');

    expect($voucher->type->value)->toBe('CRV')
        ->and($voucher->lines)->toHaveCount(2)
        ->and($voucher->lines[0]->account_code)->toBe('1111')
        ->and($voucher->lines[0]->debit_usd)->toBe('250.00')
        ->and($voucher->lines[1]->account_code)->toBe('1204')
        ->and($voucher->lines[1]->credit_usd)->toBe('250.00');

    foreach ($voucher->lines as $line) {
        expect($line->fund_source)->toBe(FundSource::RecoveredLoans)
            ->and($line->donor)->toBe($project->donor)
            ->and($line->restriction)->toBe($project->restriction)
            ->and($line->project_code)->toBe('F2F');
    }

    $first = $loan->instalments()->first();
    expect($first->paid_amount)->toBe('250.00')->and($first->paid_voucher_id)->toBe($voucher->id);
});

it('keeps a part payment on the instalment and finishes it with the next payment', function () {
    ['loan' => $loan] = openLoan();

    payLoan($loan, '100');
    $first = $loan->instalments()->first();
    expect($first->paid_amount)->toBe('100.00')->and($first->outstanding())->toBe('150.00');

    payLoan($loan, '150');
    expect($loan->instalments()->first()->outstanding())->toBe('0.00')
        ->and($loan->instalments()->skip(1)->first()->paid_amount)->toBe('0.00');
});

it('lets a payment run over into the next instalments, oldest first', function () {
    ['loan' => $loan] = openLoan();

    $voucher = payLoan($loan, '600');
    $paid = $loan->instalments()->get()->pluck('paid_amount')->all();

    expect($paid)->toBe(['250.00', '250.00', '100.00', '0.00'])
        ->and($loan->instalments()->get()->pluck('paid_voucher_id')->all())->toBe([$voucher->id, $voucher->id, $voucher->id, null]);
});

it('refuses an overpayment, a zero payment and a payment into the wrong currency of cash', function () {
    ['loan' => $loan] = openLoan();
    payLoan($loan, '900');

    expect(fn () => payLoan($loan, '100.01'))->toThrow(LoanException::class)
        ->and(fn () => payLoan($loan, '0'))->toThrow(LoanException::class)
        ->and(fn () => payLoan($loan, '50', '1112', '15000'))->toThrow(LoanException::class);

    payLoan($loan, '100');
    expect(fn () => payLoan($loan, '1'))->toThrow(LoanException::class)
        ->and(DB::table('gl_vouchers')->where('type', 'CRV')->count())->toBe(2);
});

it('receives a pound loan in pounds at the rate of the day', function () {
    ['loan' => $loan] = openLoan(['currency' => 'SYP', 'rate' => '15000', 'loan' => '12000000', 'grant' => '0', 'budget_line' => '', 'cash' => '1112', 'count' => 4]);

    expect(fn () => payLoan($loan, '3000000', '1112', ''))->toThrow(LoanException::class);

    $voucher = payLoan($loan, '3000000', '1112', '16000');

    expect($voucher->lines[0]->currency)->toBe('SYP')
        ->and($voucher->lines[0]->debit_orig)->toBe('3000000.00')
        ->and($voucher->lines[0]->debit_usd)->toBe('187.50')
        ->and($voucher->lines[1]->credit_usd)->toBe('187.50')
        ->and($loan->instalments()->first()->outstanding())->toBe('0.00');
});

it('measures the arrears on a date', function () {
    ['loan' => $loan] = openLoan();
    $loans = app(MicroLoans::class);
    payLoan($loan, '100');

    $arrears = $loans->arrears($loan->fresh(), CarbonImmutable::parse('2026-06-30'));
    $before = $loans->arrears($loan->fresh(), CarbonImmutable::parse('2026-04-29'));

    expect($arrears)->toBe(['amount' => '650.00', 'instalments' => 3, 'days' => 61])
        ->and($before)->toBe(['amount' => '0.00', 'instalments' => 0, 'days' => 0]);
});

it('feeds the loans report from approved receipts only', function () {
    ['loan' => $loan] = openLoan();
    $voucher = payLoan($loan, '250');

    $report = fn () => app(FollowUpReports::class)->run('loans', new FollowUpFilters(to: '2026-04-30'))['rows'][0];

    expect($report())->toMatchArray(['due' => '250.00', 'collected' => '0.00', 'arrears' => '250.00']);

    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => 9, 'approved_at' => now()]);

    expect($report())->toMatchArray(['collected' => '250.00', 'arrears' => '0.00']);
});

function loanUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

it('lets the accountant open a loan and receive an instalment from the screen', function () {
    $ann = loanUser(RoleId::Accountant, 'Ann');

    Livewire::actingAs($ann)->test(MicroLoansScreen::class)
        ->set('beneficiary', 'Huda')->set('project', 'F2F')->set('loanAmount', '1000')->set('grantAmount', '200')
        ->set('instalments', '4')->set('cashAccount', '1111')->set('budgetLine', 'F2F|Grants')->set('requestId', (string) approvedLoanRequest()->id)
        ->call('open')
        ->assertSet('failures', [])
        ->assertSeeHtml('data-test="loan-list"');

    $loan = MicroLoan::query()->firstOrFail();

    Livewire::actingAs($ann)->test(MicroLoansScreen::class)
        ->set('tab', 'payments')->set('loanId', (string) $loan->id)->set('paymentAmount', '250')->set('cashAccount', '1111')
        ->call('receive')
        ->assertSet('failures', [])
        ->set('paymentAmount', '5000')
        ->call('receive')
        ->assertSet('failures', [__('loans.problem.overpayment', ['owed' => '750.00', 'currency' => 'USD'])]);

    expect(DB::table('gl_vouchers')->pluck('type')->all())->toBe(['CPV', 'CRV']);
});

it('shows the loans screen to the financial manager but lets only the preparers save', function () {
    Livewire::actingAs(loanUser(RoleId::FinancialManager, 'Fatima'))->test(MicroLoansScreen::class)
        ->assertOk()
        ->assertDontSeeHtml('data-test="open-loan"')
        ->set('beneficiary', 'Huda')
        ->call('open')
        ->assertForbidden();

    $this->actingAs(loanUser(RoleId::Cashier, 'Carl'))->get(route('entries.micro-loans'))->assertForbidden();
    $this->actingAs(loanUser(RoleId::Accountant, 'Ann'))->get(route('entries.micro-loans'))->assertOk();
});

it('refuses a disbursement above the payment limit without an approved request', function () {
    expect(fn () => app(MicroLoans::class)->open(
        'Huda', 'F2F', '1000', '0', 'USD', '', CarbonImmutable::parse('2026-03-01'), 4, CarbonImmutable::parse('2026-04-30'), '1111', '', FundSource::DonorGrant, 5,
    ))->toThrow(LedgerRuleException::class);

    expect(MicroLoan::query()->count())->toBe(0);
});

it('spaces the instalments by the chosen number of months', function () {
    $rows = app(ScheduleGenerator::class)->equalMonthly('300.00', 3, CarbonImmutable::parse('2026-01-31'), 'USD', 3);

    expect(array_column($rows, 'due_date'))->toBe(['2026-01-31', '2026-04-30', '2026-07-31']);
    expect(fn () => app(ScheduleGenerator::class)->equalMonthly('300.00', 3, CarbonImmutable::parse('2026-01-31'), 'USD', 0))->toThrow(LoanException::class)
        ->and(fn () => app(ScheduleGenerator::class)->equalMonthly('300.00', 3, CarbonImmutable::parse('2026-01-31'), 'USD', 13))->toThrow(LoanException::class);
});

it('keeps the beneficiary details and the interval with the loan', function () {
    $loan = app(MicroLoans::class)->open(
        'Huda', 'F2F', '1000', '0', 'USD', '', CarbonImmutable::parse('2026-03-01'), 4, CarbonImmutable::parse('2026-04-30'), '1111', '',
        FundSource::DonorGrant, 5, approvedLoanRequest()->id, ' 01020304050 ', '+963 11 123 4567', 'Rima, Salma', 2,
    )['loan']->fresh();

    expect($loan->national_number)->toBe('01020304050')
        ->and($loan->mobile)->toBe('+963 11 123 4567')
        ->and($loan->partners)->toBe('Rima, Salma')
        ->and($loan->payment_interval_months)->toBe(2)
        ->and($loan->first_payment_date?->toDateString())->toBe('2026-04-30')
        ->and($loan->instalments->pluck('due_date')->map->toDateString()->all())->toBe(['2026-04-30', '2026-06-30', '2026-08-30', '2026-10-30']);

    $plain = openLoan(['beneficiary' => 'Omar'])['loan']->fresh();
    expect($plain->national_number)->toBeNull()->and($plain->mobile)->toBeNull()->and($plain->payment_interval_months)->toBe(1);
});

it('refuses a bad interval or a mobile number with letters', function () {
    $open = fn (string $mobile, int $interval) => fn () => app(MicroLoans::class)->open(
        'Huda', 'F2F', '1000', '0', 'USD', '', CarbonImmutable::parse('2026-03-01'), 4, CarbonImmutable::parse('2026-04-30'), '1111', '',
        FundSource::DonorGrant, 5, approvedLoanRequest()->id, '', $mobile, '', $interval,
    );

    expect($open('call me', 1))->toThrow(LoanException::class)
        ->and($open('0999', 0))->toThrow(LoanException::class)
        ->and($open('0999', 13))->toThrow(LoanException::class);
    expect(MicroLoan::query()->count())->toBe(0);
});

it('counts the late instalments the way the Windows program does and flags three or more', function () {
    ['loan' => $loan] = openLoan();
    $loans = app(MicroLoans::class);
    $at = fn (string $day) => CarbonImmutable::parse($day);

    // Four instalments, one a month from 04-30.
    $due = $loan->instalments->pluck('due_date')->map->toDateString()->all();
    expect($loans->latePayments($loan, $at($due[0])))->toBe(0)
        ->and($loans->latePayments($loan, $at($due[0])->addDay()))->toBe(1)
        ->and($loans->latePayments($loan, $at($due[2])->addDay()))->toBe(3)
        ->and($loans->latePayments($loan, $at($due[2])->addDay()) >= MicroLoans::LATE_FLAG)->toBeTrue();

    payLoan($loan, '250');
    expect($loans->latePayments($loan->fresh(), $at($due[2])->addDay()))->toBe(2);

    // A part payment leaves the instalment incomplete, so it is still late.
    payLoan($loan->fresh(), '100');
    expect($loans->latePayments($loan->fresh(), $at($due[2])->addDay()))->toBe(2);
});

it('adds a late column to the loans report and flags a beneficiary with three late instalments', function () {
    ['loan' => $loan] = openLoan();
    $due = $loan->instalments->pluck('due_date')->map->toDateString()->all();
    $run = fn () => app(FollowUpReports::class)->run('loans', new FollowUpFilters(to: CarbonImmutable::parse($due[2])->addDay()->toDateString()));

    expect(array_column($run()['columns'], 'key'))->toContain('late')
        ->and($run()['rows'][0])->toMatchArray(['late' => '3', 'late_flag' => '1']);

    // Only an approved receipt counts, as in the rest of the report.
    $voucher = payLoan($loan, '250');
    expect($run()['rows'][0])->toMatchArray(['late' => '3', 'late_flag' => '1']);

    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => 9, 'approved_at' => now()]);
    expect($run()['rows'][0])->toMatchArray(['late' => '2', 'late_flag' => '']);
});

it('opens a loan with the beneficiary details from the screen and highlights a flagged beneficiary', function () {
    $ann = loanUser(RoleId::Accountant, 'Ann');

    Livewire::actingAs($ann)->test(MicroLoansScreen::class)
        ->set('beneficiary', 'Huda')->set('nationalNumber', '0102')->set('mobile', '0999 123 456')->set('partners', 'Rima')
        ->set('project', 'F2F')->set('loanAmount', '1000')->set('grantAmount', '0')->set('instalments', '4')->set('intervalMonths', '2')
        ->set('cashAccount', '1111')->set('requestId', (string) approvedLoanRequest()->id)
        ->call('open')
        ->assertSet('failures', [])
        ->assertSet('mobile', '')
        ->assertSee('0999 123 456');

    $loan = MicroLoan::query()->firstOrFail();
    expect($loan->national_number)->toBe('0102')->and($loan->payment_interval_months)->toBe(2);

    // Make three instalments late: the row is highlighted.
    DB::table('gl_micro_instalments')->where('loan_id', $loan->id)->update(['due_date' => '2020-01-01']);
    Livewire::actingAs($ann)->test(MicroLoansScreen::class)->assertSeeHtml('data-late-flag');
});
