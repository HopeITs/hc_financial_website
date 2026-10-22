<?php

use App\Enums\AccountClass;
use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Models\GlAccount;
use App\Models\MonthClose;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Ledger\AmendApprovedVoucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\Rules\ApproverIsNotPreparer;
use App\Services\Ledger\Rules\MonthClosesOnlyWhenClearingIsZero;
use App\Services\Ledger\Rules\UsedAccountKeepsIdentity;
use Carbon\CarbonImmutable;

beforeEach(fn () => createLedgerSchema());

function postedReceipt(array $header = []): Voucher
{
    return app(PostVoucher::class)->post(ledgerReceipt($header));
}

function approved(Voucher $voucher, int $by = 9): Voucher
{
    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => $by, 'approved_at' => now()]);

    return $voucher->refresh();
}

function transfer(string $from, string $to, string $date, int $amount = 50): Voucher
{
    return approved(app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse($date)], [
        new LineDraft($to, 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft($from, 'USD', credit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ])));
}

// Rule 7

it('rule 7: lets someone other than the preparer approve', function () {
    expect((new ApproverIsNotPreparer)->check(postedReceipt(['preparedBy' => 5]), 9))->toBe([]);
});

it('rule 7: refuses the preparer approving their own voucher', function () {
    $violations = (new ApproverIsNotPreparer)->check(postedReceipt(['preparedBy' => 5]), 5);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe(7);
});

// Rule 9

it('rule 9: keeps old value, new value, who and why when an approved voucher changes', function () {
    $voucher = approved(postedReceipt());

    $changed = app(AmendApprovedVoucher::class)->amend(
        $voucher, ['date' => '2026-03-12', 'description' => 'ACN grant, corrected'], userId: 9, reason: 'Wrong date on the bank slip',
    );

    $audits = VoucherAudit::query()->where('voucher_id', $voucher->id)->where('action', 'amend')->orderBy('id')->get();

    expect($changed->date->toDateString())->toBe('2026-03-12')
        ->and($changed->description)->toBe('ACN grant, corrected')
        ->and($audits)->toHaveCount(2)
        ->and($audits[0]->field)->toBe('date')
        ->and($audits[0]->old_value)->toBe('2026-03-10')
        ->and($audits[0]->new_value)->toBe('2026-03-12')
        ->and($audits[0]->changed_by)->toBe(9)
        ->and($audits[0]->reason)->toBe('Wrong date on the bank slip')
        ->and($audits[1]->old_value)->toBe('ACN grant');
});

it('rule 9: refuses a change to an approved voucher without a reason, and leaves it untouched', function () {
    $voucher = approved(postedReceipt());

    try {
        app(AmendApprovedVoucher::class)->amend($voucher, ['description' => 'Other'], userId: 9, reason: '  ');
        $this->fail('The change should have been refused.');
    } catch (LedgerRuleException $e) {
        expect($e->rules())->toBe([9]);
    }

    expect(Voucher::query()->find($voucher->id)->description)->toBe('ACN grant')
        ->and(VoucherAudit::query()->where('action', 'amend')->count())->toBe(0);
});

it('rule 9: does not audit a voucher that is still pending, ignores unchanged values and refuses other fields', function () {
    $voucher = postedReceipt();

    app(AmendApprovedVoucher::class)->amend($voucher, ['description' => 'Pending edit'], userId: 5);
    app(AmendApprovedVoucher::class)->amend($voucher->refresh(), ['description' => 'Pending edit'], userId: 5);

    expect($voucher->refresh()->description)->toBe('Pending edit')
        ->and(VoucherAudit::query()->where('action', 'amend')->count())->toBe(0);

    expect(fn () => app(AmendApprovedVoucher::class)->amend($voucher, ['prepared_by' => 1], userId: 5))
        ->toThrow(LedgerRuleException::class);
});

it('rule 8 on amending: refuses a change to a voucher in a closed month, or into one', function () {
    $voucher = approved(postedReceipt());

    MonthClose::query()->create(['month' => '2026-04', 'closed_at' => now(), 'closed_by' => 1]);
    expect(fn () => app(AmendApprovedVoucher::class)->amend($voucher, ['date' => '2026-04-02'], 9, 'Move'))
        ->toThrow(LedgerRuleException::class);

    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);
    expect(fn () => app(AmendApprovedVoucher::class)->amend($voucher, ['description' => 'Late edit'], 9, 'Fix'))
        ->toThrow(LedgerRuleException::class)
        ->and($voucher->refresh()->description)->toBe('ACN grant');
});

// Rule 11

it('rule 11: lets an unused account change freely, and a used account change its name', function () {
    $unused = GlAccount::query()->find('4107');
    $used = GlAccount::query()->find('4101');
    postedReceipt();

    expect((new UsedAccountKeepsIdentity)->check($unused, ['code' => '4199', 'class' => AccountClass::Expenses->value, 'currency' => 'USD']))->toBe([])
        ->and((new UsedAccountKeepsIdentity)->check($used, ['name_en' => 'ACN renamed', 'is_active' => false]))->toBe([])
        ->and((new UsedAccountKeepsIdentity)->check($used, ['class' => AccountClass::Income, 'currency' => 'Any']))->toBe([]);
});

it('rule 11: refuses changing the code, class or currency of an account that vouchers use', function () {
    $used = GlAccount::query()->find('4101');
    postedReceipt();

    $violations = (new UsedAccountKeepsIdentity)->check($used, [
        'code' => '4199', 'class' => AccountClass::Expenses, 'currency' => 'USD', 'name_en' => 'Renamed',
    ]);

    expect($violations)->toHaveCount(3)
        ->and($violations[0]->rule)->toBe(11);
});

// Rule 12

it('rule 12: lets a month close when the in-transit and suspense accounts are zero', function () {
    transfer('1111', '1113', '2026-03-05');
    transfer('1113', '1111', '2026-03-20');

    expect((new MonthClosesOnlyWhenClearingIsZero)->check('2026-03'))->toBe([]);
});

it('rule 12: refuses a close while an in-transit account holds money', function () {
    transfer('1111', '1113', '2026-03-05');

    $violations = (new MonthClosesOnlyWhenClearingIsZero)->check('2026-03');

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe(12)
        ->and($violations[0]->message)->toContain('1113');
});

it('rule 12: refuses a close while the suspense account holds money, and counts approved vouchers up to month end only', function () {
    transfer('4101', '2901', '2026-03-05');

    expect((new MonthClosesOnlyWhenClearingIsZero)->check('2026-03'))->toHaveCount(1)
        ->and((new MonthClosesOnlyWhenClearingIsZero)->check('2026-02'))->toBe([]);
});

it('rule 12: ignores a voucher that is not approved yet', function () {
    app(PostVoucher::class)->post(ledgerReceipt(lines: [
        new LineDraft('1113', 'USD', debit: 50, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('1111', 'USD', credit: 50, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]));

    expect((new MonthClosesOnlyWhenClearingIsZero)->check('2026-03'))->toBe([]);
});
