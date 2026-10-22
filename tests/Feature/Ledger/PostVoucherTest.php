<?php

use App\Enums\FundSource;
use App\Enums\VoucherSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\MonthClose;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use App\Models\VoucherSequence;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use Carbon\CarbonImmutable;

beforeEach(fn () => createLedgerSchema());

function invalidDraft(): VoucherDraft
{
    return ledgerReceipt(lines: [
        new LineDraft('1111', 'USD', debit: 100),
        new LineDraft('4101', 'USD', credit: 90, projectCode: 'F2F'),
    ]);
}

it('posts a valid voucher as pending approval with its lines, a number and an audit row', function () {
    $voucher = app(PostVoucher::class)->post(ledgerReceipt());

    expect($voucher->id)->toBe('CRV-2026-0001')
        ->and($voucher->type)->toBe(VoucherType::Receipt)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->source)->toBe(VoucherSource::Web)
        ->and($voucher->prepared_by)->toBe(5)
        ->and($voucher->approved_by)->toBeNull()
        ->and($voucher->lines)->toHaveCount(2)
        ->and($voucher->lines[0]->account_code)->toBe('1111')
        ->and($voucher->lines[0]->debit_usd)->toBe('100.00')
        ->and($voucher->lines[0]->fund_source)->toBe(FundSource::DonorGrant)
        ->and($voucher->lines[1]->credit_orig)->toBe('100.00')
        ->and(VoucherAudit::query()->where('voucher_id', $voucher->id)->pluck('action')->all())->toBe(['create']);
});

it('keeps the cents on the exchange differences line when it posts', function () {
    $voucher = app(PostVoucher::class)->post(ledgerReceipt(lines: [
        new LineDraft('1112', 'SYP', debit: 1500000, rate: 15000, debitUsd: '100.01', projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 100, projectCode: 'F2F'),
    ]));

    expect($voucher->lines)->toHaveCount(3)
        ->and($voucher->lines[2]->account_code)->toBe('5503')
        ->and($voucher->lines[2]->credit_usd)->toBe('0.01')
        ->and($voucher->lines[0]->rate)->toBe('15000.000000');
});

it('stores nothing and uses no number when a rule is broken', function () {
    try {
        app(PostVoucher::class)->post(invalidDraft());
        $this->fail('The voucher should have been refused.');
    } catch (LedgerRuleException $e) {
        expect($e->rules())->toBe([4, 5]);
    }

    expect(Voucher::withTrashed()->count())->toBe(0)
        ->and(VoucherLine::query()->count())->toBe(0)
        ->and(VoucherAudit::query()->count())->toBe(0)
        ->and(VoucherSequence::query()->count())->toBe(0)
        ->and(app(PostVoucher::class)->post(ledgerReceipt())->id)->toBe('CRV-2026-0001');
});

it('reports every broken rule at once for the voucher builder check list', function () {
    $violations = app(PostVoucher::class)->check(invalidDraft());

    expect(collect($violations)->pluck('rule')->unique()->values()->all())->toBe([4, 5])
        ->and(app(PostVoucher::class)->check(ledgerReceipt()))->toBe([]);
});

it('gives the messages in the current language', function () {
    expect(app(PostVoucher::class)->check(invalidDraft())[0]->message)->toContain('out by 10.00 USD');

    app()->setLocale('ar');

    expect(app(PostVoucher::class)->check(invalidDraft())[0]->message)->toContain('10.00')->toContain('دولار');
});

it('rule 10: numbers each type and year on its own and always forward', function () {
    $post = fn (VoucherType $type, string $date) => app(PostVoucher::class)->post(ledgerReceipt([
        'type' => $type, 'date' => CarbonImmutable::parse($date),
    ]))->id;

    expect($post(VoucherType::Receipt, '2026-03-01'))->toBe('CRV-2026-0001')
        ->and($post(VoucherType::Receipt, '2026-03-02'))->toBe('CRV-2026-0002')
        ->and($post(VoucherType::Payment, '2026-03-02'))->toBe('CPV-2026-0001')
        ->and($post(VoucherType::Receipt, '2027-01-05'))->toBe('CRV-2027-0001');
});

it('rule 10: never gives a number again, not even one of a deleted voucher or after the counter is reset', function () {
    $first = app(PostVoucher::class)->post(ledgerReceipt());
    $second = app(PostVoucher::class)->post(ledgerReceipt());
    $second->delete();

    expect(app(PostVoucher::class)->post(ledgerReceipt())->id)->toBe('CRV-2026-0003');

    VoucherSequence::query()->where('type', 'CRV')->where('year', 2026)->update(['last_number' => 0]);

    expect($first->id)->toBe('CRV-2026-0001')
        ->and(app(PostVoucher::class)->post(ledgerReceipt())->id)->toBe('CRV-2026-0004');
});

it('rule 13: keeps the legacy id of a migrated voucher and refuses to migrate it twice', function () {
    $draft = ledgerReceipt(['source' => VoucherSource::Migration, 'legacyActionId' => 4321]);

    $voucher = app(PostVoucher::class)->post($draft);

    expect($voucher->legacy_action_id)->toBe(4321)
        ->and($voucher->source)->toBe(VoucherSource::Migration)
        ->and(fn () => app(PostVoucher::class)->post($draft))->toThrow(LedgerRuleException::class)
        ->and(Voucher::query()->count())->toBe(1);
});

it('refuses a voucher dated in a closed month', function () {
    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);

    expect(fn () => app(PostVoucher::class)->post(ledgerReceipt()))->toThrow(LedgerRuleException::class)
        ->and(Voucher::query()->count())->toBe(0);
});
