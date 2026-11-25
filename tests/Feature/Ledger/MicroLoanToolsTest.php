<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Livewire\ExcelTools;
use App\Livewire\MicroLoans as MicroLoansScreen;
use App\Models\GlBudgetLine;
use App\Models\LedgerRequest;
use App\Models\MicroLoan;
use App\Models\MicroPayment;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\PendingVoucherChanges;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroLoans;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    seedChartWithTestProjects();

    GlBudgetLine::query()->create(['key' => 'F2F|Micro grants', 'project_code' => 'F2F', 'name' => 'Micro grants', 'account_code' => '5413', 'budget' => 100000, 'currency' => 'USD']);
});

function mlUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** A 400 $ loan with no grant in four instalments of 100 $, from 2026-04-30. */
function mlLoan(): MicroLoan
{
    $request = LedgerRequest::query()->create(['type' => 'purchase', 'project_code' => 'F2F', 'amount' => '5000.00', 'currency' => 'USD', 'status' => 'approved', 'requested_by' => 3]);

    return app(MicroLoans::class)->open(
        'Huda', 'F2F', '400', '0', 'USD', '', CarbonImmutable::parse('2026-03-01'), 4, CarbonImmutable::parse('2026-04-30'),
        '1111', '', FundSource::DonorGrant, 5, $request->id,
    )['loan'];
}

function mlPay(MicroLoan $loan, string $amount)
{
    return app(MicroLoans::class)->receive($loan->fresh(), CarbonImmutable::parse('2026-05-10'), $amount, '1111', 5);
}

it('keeps each repayment with the instalments it settled, and gives them back when its voucher is deleted or rejected', function () {
    $loan = mlLoan();
    $ann = mlUser(RoleId::Accountant, 'Ann');
    $first = mlPay($loan, '150');
    $second = mlPay($loan, '50');

    expect(MicroPayment::query()->pluck('amount')->map(fn ($amount) => (string) $amount)->all())->toBe(['150.00', '50.00'])
        ->and($loan->fresh()->owed())->toBe('200.00');

    app(PendingVoucherChanges::class)->delete($second, $ann, 'Typed twice');
    expect($loan->fresh()->owed())->toBe('250.00');

    app(VoucherApprovals::class)->reject($first, mlUser(RoleId::FinancialManager, 'Fadi'), 'No receipt');
    expect($loan->fresh()->owed())->toBe('400.00')
        ->and(MicroPayment::query()->count())->toBe(0);
});

it('writes off what is left to the project\'s grants line, and the report no longer counts it as due', function () {
    $loan = mlLoan();
    mlPay($loan, '100');

    expect(fn () => app(MicroLoans::class)->writeOff($loan->fresh(), CarbonImmutable::parse('2026-06-01'), '', 5))->toThrow(LoanException::class);

    $voucher = app(MicroLoans::class)->writeOff($loan->fresh(), CarbonImmutable::parse('2026-06-01'), 'Business closed', 5);

    expect($voucher->type->value)->toBe('JV')
        ->and($voucher->lines->map(fn ($line): array => [$line->account_code, $line->budget_line_key, (string) $line->debit_usd, (string) $line->credit_usd])->all())->toBe([
            ['5413', 'F2F|Micro grants', '300.00', '0.00'],
            ['1204', null, '0.00', '300.00'],
        ])
        ->and($loan->fresh()->owed())->toBe('0.00')
        ->and((string) $loan->instalments()->sum('written_off_amount'))->toEqual('300');

    $row = app(App\Services\Ledger\FollowUpReports::class)->run('loans', new App\Services\Ledger\FollowUpFilters(to: '2026-12-31'))['rows'][0];
    expect($row['due'])->toBe('100.00');
});

it('spreads what is owed over a new schedule, keeping what was paid', function () {
    $loan = mlLoan();
    mlPay($loan, '150');

    app(MicroLoans::class)->reschedule($loan->fresh(), 5, CarbonImmutable::parse('2026-07-01'), 2);
    $loan = $loan->fresh(['instalments']);

    expect($loan->owed())->toBe('250.00')
        ->and($loan->instalments->map(fn ($instalment): array => [$instalment->due_date->toDateString(), (string) $instalment->amount, (string) $instalment->paid_amount])->all())->toBe([
            ['2026-04-30', '100.00', '100.00'],
            ['2026-05-30', '50.00', '50.00'],
            ['2026-07-01', '50.00', '0.00'],
            ['2026-09-01', '50.00', '0.00'],
            ['2026-11-01', '50.00', '0.00'],
            ['2027-01-01', '50.00', '0.00'],
            ['2027-03-01', '50.00', '0.00'],
        ])
        ->and($loan->payment_interval_months)->toBe(2);
});

it('deletes a loan with its disbursement while that waits for approval, and not once it is given', function () {
    $ann = mlUser(RoleId::Accountant, 'Ann');
    $loan = mlLoan();
    $voucherId = $loan->disbursement_voucher_id;

    app(MicroLoans::class)->delete($loan, $ann, 'Wrong beneficiary');

    expect(MicroLoan::query()->count())->toBe(0)
        ->and(Voucher::withTrashed()->find($voucherId)?->trashed())->toBeTrue();

    $given = mlLoan();
    Voucher::query()->whereKey($given->disbursement_voucher_id)->update(['status' => VoucherStatus::Approved->value]);

    expect(fn () => app(MicroLoans::class)->delete($given->fresh(), $ann, 'Oops'))->toThrow(LoanException::class);
});

it('opens a loan card to change the beneficiary\'s details and to pay off the loan', function () {
    $loan = mlLoan();
    mlPay($loan, '100');

    Livewire::actingAs(mlUser(RoleId::Accountant, 'Ann'))->test(MicroLoansScreen::class)
        ->call('openCard', $loan->id)
        ->assertSeeHtml('data-test="loan-card"')
        ->assertSeeHtml('data-test="card-schedule"')
        ->assertSeeHtml('data-test="card-payments"')
        ->set('cardMobile', '+963 944 000 111')
        ->call('saveDetails')
        ->assertSee(__('loans.details_saved'))
        ->call('payOff')
        ->assertSet('tab', 'payments')
        ->assertSet('loanId', (string) $loan->id)
        ->assertSet('paymentAmount', '300.00');

    expect($loan->fresh()->mobile)->toBe('+963 944 000 111');
});

it('exports the loans to Excel but opens no loan from it: a loan goes through a micro-project file', function () {
    $ann = mlUser(RoleId::Accountant, 'Ann');
    // The direct loan from a project is closed (finance decision of 2026-11-25): no import button, and a forced save
    // opens nothing.
    Livewire::actingAs($ann)->test(ExcelTools::class, ['kind' => 'micro_loans'])
        ->assertDontSeeHtml('data-test="excel-import-micro_loans"')
        ->call('save')
        ->assertForbidden();

    expect(MicroLoan::query()->count())->toBe(0)
        ->and(Voucher::query()->count())->toBe(0);

    Livewire::actingAs($ann)->test(ExcelTools::class, ['kind' => 'micro_loans'])
        ->call('export')->assertFileDownloaded('micro-loans-'.now()->format('Y-m-d').'.xlsx');

    Livewire::actingAs($ann)->test(ExcelTools::class, ['kind' => 'loan_schedule'])
        ->assertDontSeeHtml('data-test="excel-import-loan_schedule"')
        ->call('export')->assertFileDownloaded('micro-loan-schedule-'.now()->format('Y-m-d').'.xlsx');
});
