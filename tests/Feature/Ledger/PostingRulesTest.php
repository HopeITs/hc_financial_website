<?php

use App\Enums\FundSource;
use App\Enums\VoucherSource;
use App\Models\GlAccount;
use App\Models\MonthClose;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\RoundingLine;
use App\Services\Ledger\Rules\BalancedInUsd;
use App\Services\Ledger\Rules\BudgetLineMatchesProjectAndAccount;
use App\Services\Ledger\Rules\CashAccountInOwnCurrency;
use App\Services\Ledger\Rules\DebitOrCreditNeverBoth;
use App\Services\Ledger\Rules\MigratedVoucherKeepsLegacyId;
use App\Services\Ledger\Rules\MonthIsOpen;
use App\Services\Ledger\Rules\OnlyActivePostingAccounts;
use App\Services\Ledger\Rules\PostingRule;
use App\Services\Ledger\Rules\RequiredTagsFilled;
use App\Services\Ledger\VoucherDraft;

beforeEach(fn () => createLedgerSchema());

/** @return list<int> the line numbers a rule complains about (0 for a rule about the whole voucher) */
function ruleLines(PostingRule $rule, VoucherDraft $draft): array
{
    return array_map(fn ($v) => $v->line ?? 0, $rule->check($draft, LedgerContext::forDraft($draft)));
}

// Rule 1

it('rule 1: accepts active posting accounts', function () {
    expect(ruleLines(new OnlyActivePostingAccounts, ledgerReceipt()))->toBe([]);
});

it('rule 1: refuses a header, an inactive account and an unknown account', function () {
    GlAccount::query()->find('4101')->update(['is_active' => false]);

    $draft = ledgerReceipt(lines: [
        new LineDraft('1110', 'USD', debit: 10),
        new LineDraft('4101', 'USD', credit: 10),
        new LineDraft('9999', 'USD', credit: 10),
    ]);

    expect(ruleLines(new OnlyActivePostingAccounts, $draft))->toBe([1, 2, 3]);
});

// Rule 2

it('rule 2: accepts a cash account in its own currency and an any-currency account in USD or SYP', function () {
    $draft = ledgerReceipt(lines: [
        new LineDraft('1112', 'SYP', debit: 1000000, debitUsd: 100),
        new LineDraft('4101', 'SYP', credit: 1000000, creditUsd: 100),
    ]);

    expect(ruleLines(new CashAccountInOwnCurrency, $draft))->toBe([]);
});

it('rule 2: refuses a cash account in the other currency and a currency the books do not know', function () {
    $draft = ledgerReceipt(lines: [
        new LineDraft('1111', 'SYP', debit: 100, debitUsd: 100),
        new LineDraft('1112', 'USD', debit: 100),
        new LineDraft('4101', 'GBP', credit: 100, creditUsd: 100),
    ]);

    expect(ruleLines(new CashAccountInOwnCurrency, $draft))->toBe([1, 2, 3]);
});

// Rule 3

it('rule 3: accepts a line that is a debit or a credit', function () {
    expect(ruleLines(new DebitOrCreditNeverBoth, ledgerReceipt()))->toBe([]);
});

it('rule 3: refuses both sides, a negative amount, no amount, and both sides in USD only', function () {
    $draft = ledgerReceipt(lines: [
        new LineDraft('1111', 'USD', debit: 10, credit: 10),
        new LineDraft('1111', 'USD', debit: -5),
        new LineDraft('1111', 'USD'),
        new LineDraft('1111', 'SYP', debit: 100, debitUsd: 1, creditUsd: 1),
        new LineDraft('1111', 'USD', debit: 10),
    ]);

    expect(ruleLines(new DebitOrCreditNeverBoth, $draft))->toBe([1, 2, 3, 4]);
});

// Rule 4

it('rule 4: accepts a voucher that balances exactly in USD', function () {
    expect(ruleLines(new BalancedInUsd, ledgerReceipt()))->toBe([]);
});

it('rule 4: refuses a voucher that is out by a cent, and one with a single line', function () {
    $outByACent = ledgerReceipt(lines: [
        new LineDraft('1111', 'USD', debit: '100.01'),
        new LineDraft('4101', 'USD', credit: 100),
    ]);
    $oneLine = ledgerReceipt(lines: [new LineDraft('1111', 'USD', debit: 100)]);

    expect(ruleLines(new BalancedInUsd, $outByACent))->toBe([0])
        ->and(ruleLines(new BalancedInUsd, $oneLine))->toBe([0]);
});

it('rule 4: moves a few cents to the exchange differences account and leaves a big gap alone', function () {
    $syp = ledgerReceipt(lines: [
        new LineDraft('1112', 'SYP', debit: 1500000, debitUsd: '100.01', projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 100, projectCode: 'F2F'),
    ]);
    $balanced = (new RoundingLine)->balance($syp);
    $added = $balanced->lines[2];

    expect($balanced->lines)->toHaveCount(3)
        ->and($added->accountCode)->toBe('5503')
        ->and($added->creditUsd)->toBe('0.01')
        ->and($added->debitUsd)->toBe('0.00')
        ->and($added->projectCode)->toBe('F2F')
        ->and(BalancedInUsd::difference($balanced))->toBe('0.00');

    $credits = (new RoundingLine)->balance(ledgerReceipt(lines: [
        new LineDraft('1111', 'USD', debit: 100, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: '100.02', projectCode: 'F2F'),
    ]));

    expect($credits->lines[2]->debitUsd)->toBe('0.02');

    $bigGap = ledgerReceipt(lines: [
        new LineDraft('1111', 'USD', debit: 100),
        new LineDraft('4101', 'USD', credit: 90),
    ]);

    expect((new RoundingLine)->balance($bigGap)->lines)->toHaveCount(2);
});

// Rule 5

it('rule 5: accepts a line with all the tags its account requires', function () {
    expect(ruleLines(new RequiredTagsFilled, ledgerReceipt()))->toBe([]);
});

it('rule 5: refuses a line with a required tag missing or blank', function () {
    $draft = ledgerReceipt(lines: [
        new LineDraft('1111', 'USD', debit: 100, projectCode: 'F2F'),
        new LineDraft('4101', 'USD', credit: 100, projectCode: '  '),
    ]);

    expect(ruleLines(new RequiredTagsFilled, $draft))->toBe([1, 2]);
});

// Rule 6

it('rule 6: accepts a budget line used with its own project and account', function () {
    $draft = ledgerReceipt(lines: [
        new LineDraft('5405', 'USD', debit: 50, projectCode: 'F2F', budgetLineKey: 'F2F|Maintenance'),
        new LineDraft('1111', 'USD', credit: 50, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);

    expect(ruleLines(new BudgetLineMatchesProjectAndAccount, $draft))->toBe([]);
});

it('rule 6: refuses a budget line of another project, another account or one that does not exist', function () {
    $draft = ledgerReceipt(lines: [
        new LineDraft('5405', 'USD', debit: 50, projectCode: 'SZ', budgetLineKey: 'F2F|Maintenance'),
        new LineDraft('5403', 'USD', debit: 50, projectCode: 'F2F', budgetLineKey: 'F2F|Maintenance'),
        new LineDraft('5405', 'USD', debit: 50, projectCode: 'F2F', budgetLineKey: 'F2F|Nothing'),
    ]);

    expect(ruleLines(new BudgetLineMatchesProjectAndAccount, $draft))->toBe([1, 2, 3]);
});

// Rule 8

it('rule 8: accepts a voucher in an open month', function () {
    MonthClose::query()->create(['month' => '2026-02', 'closed_at' => now(), 'closed_by' => 1]);

    expect(ruleLines(new MonthIsOpen, ledgerReceipt()))->toBe([]);
});

it('rule 8: refuses a voucher dated in a closed month', function () {
    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);

    expect(ruleLines(new MonthIsOpen, ledgerReceipt()))->toBe([0]);
});

// Rule 13

it('rule 13: accepts a migrated voucher with a new legacy id and a web voucher with none', function () {
    $migrated = ledgerReceipt(['source' => VoucherSource::Migration, 'legacyActionId' => 1234]);

    expect(ruleLines(new MigratedVoucherKeepsLegacyId, $migrated))->toBe([])
        ->and(ruleLines(new MigratedVoucherKeepsLegacyId, ledgerReceipt()))->toBe([]);
});

it('rule 13: refuses a migrated voucher without a legacy id, a repeated one, and a web voucher that has one', function () {
    app(PostVoucher::class)->post(ledgerReceipt(['source' => VoucherSource::Migration, 'legacyActionId' => 1234]));

    expect(ruleLines(new MigratedVoucherKeepsLegacyId, ledgerReceipt(['source' => VoucherSource::Migration])))->toBe([0])
        ->and(ruleLines(new MigratedVoucherKeepsLegacyId, ledgerReceipt(['source' => VoucherSource::Migration, 'legacyActionId' => 1234])))->toBe([0])
        ->and(ruleLines(new MigratedVoucherKeepsLegacyId, ledgerReceipt(['legacyActionId' => 77])))->toBe([0]);
});
