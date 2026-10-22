<?php

use App\Data\LineDraft;
use App\Data\VoucherDraft;
use App\Enums\AccountClass;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\LedgerRequest;
use App\Models\MonthClose;
use App\Models\Voucher;
use App\Services\Ledger\PostingRuleViolation;
use App\Services\Ledger\Rules\ApprovedChangesAreAudited;
use App\Services\Ledger\Rules\ApproverIsNeverThePreparer;
use App\Services\Ledger\Rules\BalancedInUsd;
use App\Services\Ledger\Rules\BudgetLineMatchesProjectAndAccount;
use App\Services\Ledger\Rules\CashAccountInOwnCurrency;
use App\Services\Ledger\Rules\DebitOrCreditNeverBoth;
use App\Services\Ledger\Rules\MigratedVouchersKeepTheirLegacyId;
use App\Services\Ledger\Rules\MonthClosesOnlyWhenClearingIsZero;
use App\Services\Ledger\Rules\NothingPostsIntoAClosedMonth;
use App\Services\Ledger\Rules\NumbersAreNeverReused;
use App\Services\Ledger\Rules\OnlyActivePostingAccounts;
use App\Services\Ledger\Rules\RequestRequiredAboveLimit;
use App\Services\Ledger\Rules\RequiredTagsFilled;
use App\Services\Ledger\Rules\UsedAccountKeepsItsIdentity;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;

beforeEach(function () {
    createFinanceSchema();
    $this->seed(ChartOfAccountsSeeder::class);
});

/** @param array<string, mixed> $extra */
function usdLine(string $account, string $side, string $amount, array $extra = []): LineDraft
{
    return new LineDraft($extra + [
        'account_code' => $account, 'currency' => 'USD', 'project_code' => 'F2F', 'fund_source' => 'donor_grant',
        $side.'_orig' => $amount, $side.'_usd' => $amount,
    ]);
}

/** A balanced 100.00 receipt unless the lines are replaced. */
function receipt(?array $lines = null, array $extra = []): VoucherDraft
{
    return new VoucherDraft(
        type: $extra['type'] ?? VoucherType::Receipt,
        date: $extra['date'] ?? Carbon::parse('2026-03-01'),
        preparedBy: 5,
        lines: $lines ?? [usdLine('1111', 'debit', '100.00'), usdLine('4101', 'credit', '100.00')],
        requestId: $extra['requestId'] ?? null,
        source: $extra['source'] ?? 'web',
        legacyActionId: $extra['legacyActionId'] ?? null,
    );
}

function violation(callable $check): ?string
{
    try {
        $check();
    } catch (PostingRuleViolation $e) {
        return $e->rule;
    }

    return null;
}

function approvedVoucher(string $id, string $date, array $lines): Voucher
{
    $voucher = Voucher::query()->create([
        'id' => $id, 'type' => VoucherType::Journal, 'date' => $date, 'status' => VoucherStatus::Approved, 'prepared_by' => 5, 'approved_by' => 4,
    ]);
    foreach ($lines as $i => $line) {
        $voucher->lines()->create($line + ['line_no' => $i + 1, 'currency' => 'USD']);
    }

    return $voucher;
}

it('rule 1: refuses a header, an unknown or an inactive account', function () {
    $rule = new OnlyActivePostingAccounts;

    expect(violation(fn () => $rule->check(receipt())))->toBeNull();

    foreach (['1110', '9999'] as $bad) {
        expect(violation(fn () => $rule->check(receipt([usdLine($bad, 'debit', '100.00'), usdLine('4101', 'credit', '100.00')]))))
            ->toBe('active_posting_account');
    }

    GlAccount::query()->whereKey('1111')->update(['is_active' => false]);
    expect(violation(fn () => $rule->check(receipt())))->toBe('active_posting_account');
});

it('rule 2: a cash account takes only its own currency', function () {
    $rule = new CashAccountInOwnCurrency;
    $syp = usdLine('1114', 'debit', '1000.00', ['currency' => 'SYP']);

    expect(violation(fn () => $rule->check(receipt([$syp, usdLine('4101', 'credit', '1000.00')]))))->toBeNull()
        ->and(violation(fn () => $rule->check(receipt([usdLine('1114', 'debit', '100.00'), usdLine('4101', 'credit', '100.00')]))))->toBe('cash_currency')
        ->and(violation(fn () => $rule->check(receipt([usdLine('1111', 'debit', '100.00', ['currency' => 'SYP']), usdLine('4101', 'credit', '100.00')]))))->toBe('cash_currency');
});

it('rule 3: a line is a debit or a credit, never both and never negative', function () {
    $rule = new DebitOrCreditNeverBoth;
    $both = usdLine('1111', 'debit', '100.00', ['credit_usd' => '5.00']);
    $negative = usdLine('1111', 'debit', '-1.00');

    expect(violation(fn () => $rule->check(receipt())))->toBeNull()
        ->and(violation(fn () => $rule->check(receipt([$both, usdLine('4101', 'credit', '95.00')]))))->toBe('debit_and_credit')
        ->and(violation(fn () => $rule->check(receipt([$negative, usdLine('4101', 'credit', '-1.00')]))))->toBe('negative_amount');
});

it('rule 4: the voucher balances exactly in USD', function () {
    $rule = new BalancedInUsd;

    expect(violation(fn () => $rule->check(receipt())))->toBeNull()
        ->and(violation(fn () => $rule->check(receipt([usdLine('1111', 'debit', '100.00'), usdLine('4101', 'credit', '99.99')]))))->toBe('unbalanced')
        ->and(violation(fn () => $rule->check(receipt([usdLine('1111', 'debit', '100.00')]))))->toBe('too_few_lines')
        ->and(violation(fn () => $rule->check(receipt([usdLine('1111', 'debit', '100.00', ['debit_usd' => '99.00']), usdLine('4101', 'credit', '99.00')]))))->toBe('usd_line_mismatch');
});

it('rule 4: an SYP line balances through its own USD amount, rounding cents on 5503', function () {
    $rule = new BalancedInUsd;
    $syp = new LineDraft(['account_code' => '1114', 'currency' => 'SYP', 'debit_orig' => '1000.00', 'rate' => '0.000077', 'debit_usd' => '0.08']);
    $income = usdLine('4101', 'credit', '0.08');

    expect(violation(fn () => $rule->check(receipt([$syp, $income]))))->toBeNull();

    $rounding = usdLine('5503', 'debit', '0.01');
    $income = usdLine('4101', 'credit', '0.09');
    expect(violation(fn () => $rule->check(receipt([$syp, $rounding, $income]))))->toBeNull();
});

it('rule 5: every required tag is filled on the line', function () {
    $rule = new RequiredTagsFilled;

    expect(violation(fn () => $rule->check(receipt())))->toBeNull()
        ->and(violation(fn () => $rule->check(receipt([usdLine('1111', 'debit', '100.00', ['fund_source' => null]), usdLine('4101', 'credit', '100.00')]))))->toBe('required_tag')
        ->and(violation(fn () => $rule->check(receipt([usdLine('1111', 'debit', '100.00'), usdLine('4101', 'credit', '100.00', ['project_code' => '  '])]))))->toBe('required_tag');
});

it('rule 6: a budget line belongs to the line project and fills its account', function () {
    $rule = new BudgetLineMatchesProjectAndAccount;
    $maintenance = fn (array $extra = []) => usdLine('5405', 'debit', '100.00', $extra + ['budget_line_key' => 'F2F|Maintenance', 'center' => 'Aleppo']);
    $cash = usdLine('1111', 'credit', '100.00');

    expect(violation(fn () => $rule->check(receipt([$maintenance(), $cash]))))->toBeNull()
        ->and(violation(fn () => $rule->check(receipt([$maintenance(['project_code' => 'SZ']), $cash]))))->toBe('budget_line_project')
        ->and(violation(fn () => $rule->check(receipt([$maintenance(['account_code' => '5411']), $cash]))))->toBe('budget_line_account')
        ->and(violation(fn () => $rule->check(receipt([$maintenance(['budget_line_key' => 'F2F|Nothing']), $cash]))))->toBe('budget_line_unknown');
});

it('rule 6: a budget line limit is not a posting rule', function () {
    $rule = new BudgetLineMatchesProjectAndAccount;
    $huge = usdLine('5405', 'debit', '999999.00', ['budget_line_key' => 'F2F|Maintenance', 'center' => 'Aleppo']);

    expect(violation(fn () => $rule->check(receipt([$huge, usdLine('1111', 'credit', '999999.00')]))))->toBeNull();
});

it('rule 7: the approver is never the preparer', function () {
    $voucher = approvedVoucher('JV-2026-0001', '2026-03-01', []);
    $rule = new ApproverIsNeverThePreparer;

    expect(violation(fn () => $rule->check($voucher, 4)))->toBeNull()
        ->and(violation(fn () => $rule->check($voucher, 5)))->toBe('approver_is_preparer');
});

it('rule 8: nothing posts into a closed month', function () {
    $rule = new NothingPostsIntoAClosedMonth;
    MonthClose::query()->create(['month' => '2026-02', 'closed_at' => now(), 'closed_by' => 2]);

    expect(violation(fn () => $rule->check(receipt())))->toBeNull()
        ->and(violation(fn () => $rule->check(receipt(extra: ['date' => Carbon::parse('2026-02-28')]))))->toBe('closed_month');
});

it('rule 9: a change to an approved voucher needs a user and a reason', function () {
    $rule = new ApprovedChangesAreAudited;
    $approved = approvedVoucher('JV-2026-0001', '2026-03-01', []);
    $pending = Voucher::query()->create(['id' => 'JV-2026-0002', 'type' => 'JV', 'date' => '2026-03-01', 'status' => 'pending_approval', 'prepared_by' => 5]);

    expect(violation(fn () => $rule->check($approved, 4, 'Wrong date')))->toBeNull()
        ->and(violation(fn () => $rule->check($approved, 4, '  ')))->toBe('change_needs_reason')
        ->and(violation(fn () => $rule->check($approved, null, 'Wrong date')))->toBe('change_needs_reason')
        ->and(violation(fn () => $rule->check($pending, 5, null)))->toBeNull();
});

it('rule 10: a voucher number is never reused, rejected ones included', function () {
    $rule = new NumbersAreNeverReused;
    $gone = Voucher::query()->create(['id' => 'CRV-2026-0007', 'type' => 'CRV', 'date' => '2026-03-01', 'status' => 'pending_approval', 'prepared_by' => 5]);
    $gone->delete();

    expect(violation(fn () => $rule->check('CRV-2026-0008')))->toBeNull()
        ->and(violation(fn () => $rule->check('CRV-2026-0007')))->toBe('number_reused');
});

it('rule 11: a used account keeps its code, class and currency', function () {
    $rule = new UsedAccountKeepsItsIdentity;
    $account = GlAccount::query()->findOrFail('1111');

    expect(violation(fn () => $rule->check($account, ['code' => '1199', 'currency' => 'SYP'])))->toBeNull();

    approvedVoucher('JV-2026-0001', '2026-03-01', [['account_code' => '1111', 'debit_orig' => '1.00', 'debit_usd' => '1.00']]);

    expect(violation(fn () => $rule->check($account, ['name_en' => 'Renamed', 'currency' => 'USD'])))->toBeNull()
        ->and(violation(fn () => $rule->check($account, ['currency' => 'SYP'])))->toBe('used_account_locked')
        ->and(violation(fn () => $rule->check($account, ['class' => AccountClass::Expenses])))->toBe('used_account_locked')
        ->and(violation(fn () => $rule->check($account, ['code' => '1199'])))->toBe('used_account_locked');
});

it('rule 12: a month closes only when in transit and suspense are zero', function () {
    $rule = new MonthClosesOnlyWhenClearingIsZero;

    expect(violation(fn () => $rule->check('2026-03')))->toBeNull();

    approvedVoucher('JV-2026-0001', '2026-03-10', [
        ['account_code' => '1113', 'debit_orig' => '50.00', 'debit_usd' => '50.00'],
        ['account_code' => '1111', 'credit_orig' => '50.00', 'credit_usd' => '50.00'],
    ]);
    expect(violation(fn () => $rule->check('2026-03')))->toBe('month_close_blocked')
        ->and(violation(fn () => $rule->check('2026-02')))->toBeNull();

    approvedVoucher('JV-2026-0002', '2026-03-20', [
        ['account_code' => '1113', 'credit_orig' => '50.00', 'credit_usd' => '50.00'],
        ['account_code' => '1111', 'debit_orig' => '50.00', 'debit_usd' => '50.00'],
        ['account_code' => '2901', 'debit_orig' => '5.00', 'debit_usd' => '5.00'],
        ['account_code' => '1112', 'credit_orig' => '5.00', 'credit_usd' => '5.00'],
    ]);
    expect(violation(fn () => $rule->check('2026-03')))->toBe('month_close_blocked');
});

it('rule 13: a migrated voucher keeps its legacy id, once', function () {
    $rule = new MigratedVouchersKeepTheirLegacyId;

    expect(violation(fn () => $rule->check(receipt())))->toBeNull()
        ->and(violation(fn () => $rule->check(receipt(extra: ['source' => 'migration']))))->toBe('legacy_id_missing')
        ->and(violation(fn () => $rule->check(receipt(extra: ['legacyActionId' => 12]))))->toBe('legacy_id_on_web_voucher')
        ->and(violation(fn () => $rule->check(receipt(extra: ['source' => 'migration', 'legacyActionId' => 12]))))->toBeNull();

    Voucher::query()->create(['id' => 'CRV-2026-0001', 'type' => 'CRV', 'date' => '2026-03-01', 'status' => 'approved', 'prepared_by' => 5, 'source' => 'migration', 'legacy_action_id' => 12]);
    expect(violation(fn () => $rule->check(receipt(extra: ['source' => 'migration', 'legacyActionId' => 12]))))->toBe('legacy_id_taken');
});

it('decision: a payment above USD 500 needs an approved request, the budget line limit is no rule', function () {
    $rule = new RequestRequiredAboveLimit;
    $payment = fn (string $amount, ?int $requestId = null) => receipt([
        usdLine('5405', 'debit', $amount, ['budget_line_key' => 'F2F|Maintenance', 'center' => 'Aleppo']),
        usdLine('1111', 'credit', $amount),
    ], ['type' => VoucherType::Payment, 'requestId' => $requestId]);

    expect(violation(fn () => $rule->check($payment('500.00'))))->toBeNull()
        ->and(violation(fn () => $rule->check($payment('500.01'))))->toBe('request_required')
        ->and(violation(fn () => $rule->check(receipt())))->toBeNull();

    $request = fn (string $status) => LedgerRequest::query()->create([
        'type' => 'purchase', 'project_code' => 'F2F', 'amount' => '900.00', 'currency' => 'USD', 'status' => $status, 'requested_by' => 3,
    ]);
    $submitted = $request('submitted');
    $approved = $request('approved');

    expect(violation(fn () => $rule->check($payment('900.00', $submitted->id))))->toBe('request_not_approved')
        ->and(violation(fn () => $rule->check($payment('900.00', 999))))->toBe('request_not_approved')
        ->and(violation(fn () => $rule->check($payment('900.00', $approved->id))))->toBeNull();
});

it('decision: an SYP payment is compared at its own voucher rate', function () {
    $rule = new RequestRequiredAboveLimit;
    $syp = fn (string $usd) => receipt([
        new LineDraft(['account_code' => '5405', 'currency' => 'SYP', 'debit_orig' => '9000000.00', 'rate' => '0.0001', 'debit_usd' => $usd, 'project_code' => 'F2F']),
        usdLine('1111', 'credit', $usd),
    ], ['type' => VoucherType::Payment]);

    expect(violation(fn () => $rule->check($syp('450.00'))))->toBeNull()
        ->and(violation(fn () => $rule->check($syp('900.00'))))->toBe('request_required');
});
