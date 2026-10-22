<?php

use App\Enums\FundSource;
use App\Enums\VoucherSource;
use App\Enums\VoucherType;
use App\Models\LedgerRequest;
use App\Models\Voucher;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\Rules\RequestRequiredAboveLimit;
use App\Services\Ledger\VoucherDraft;

beforeEach(fn () => createLedgerSchema());

function payment(string $amount, ?int $requestId = null, array $header = []): VoucherDraft
{
    return ledgerReceipt($header + ['type' => VoucherType::Payment, 'requestId' => $requestId], [
        new LineDraft('5405', 'USD', debit: $amount, projectCode: 'F2F', budgetLineKey: 'F2F|Maintenance', center: 'Aleppo'),
        new LineDraft('1111', 'USD', credit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);
}

function requestRuleBreaks(VoucherDraft $draft): int
{
    return count((new RequestRequiredAboveLimit)->check($draft, LedgerContext::forDraft($draft)));
}

function makeRequest(string $status): LedgerRequest
{
    return LedgerRequest::query()->create([
        'type' => 'purchase', 'project_code' => 'F2F', 'amount' => '900.00', 'currency' => 'USD', 'status' => $status, 'requested_by' => 3,
    ]);
}

it('accepts a payment up to USD 500 without a request and refuses one cent more', function () {
    expect(requestRuleBreaks(payment('500.00')))->toBe(0)
        ->and(requestRuleBreaks(payment('500.01')))->toBe(1);
});

it('does not hold receipts or migrated payments to the limit', function () {
    expect(requestRuleBreaks(ledgerReceipt()))->toBe(0)
        ->and(requestRuleBreaks(ledgerReceipt(['type' => VoucherType::Receipt], [
            new LineDraft('1111', 'USD', debit: 900, projectCode: 'F2F'),
            new LineDraft('4101', 'USD', credit: 900, projectCode: 'F2F'),
        ])))->toBe(0)
        ->and(requestRuleBreaks(payment('900.00', header: ['source' => VoucherSource::Migration, 'legacyActionId' => 4])))->toBe(0);
});

it('lets a payment above the limit through only with an approved request', function () {
    $submitted = makeRequest('submitted');
    $approved = makeRequest('approved');

    expect(requestRuleBreaks(payment('900.00', $approved->id)))->toBe(0)
        ->and(requestRuleBreaks(payment('900.00', $submitted->id)))->toBe(1)
        ->and(requestRuleBreaks(payment('900.00', 999)))->toBe(1);
});

it('compares an SYP payment at its own voucher rate', function () {
    $syp = fn (string $usd) => ledgerReceipt(['type' => VoucherType::Payment], [
        new LineDraft('5405', 'SYP', debit: 9000000, rate: '0.0001', debitUsd: $usd, projectCode: 'F2F', budgetLineKey: 'F2F|Maintenance', center: 'Aleppo'),
        new LineDraft('1111', 'USD', credit: $usd, projectCode: 'F2F'),
    ]);

    expect(requestRuleBreaks($syp('450.00')))->toBe(0)
        ->and(requestRuleBreaks($syp('900.00')))->toBe(1);
});

it('refuses the post without a request and saves the request link when there is one', function () {
    expect(fn () => app(PostVoucher::class)->post(payment('900.00')))->toThrow(LedgerRuleException::class)
        ->and(Voucher::withTrashed()->count())->toBe(0);

    $voucher = app(PostVoucher::class)->post(payment('900.00', makeRequest('approved')->id));

    expect($voucher->id)->toBe('CPV-2026-0001')
        ->and($voucher->request_id)->not->toBeNull();
});

it('does not stop a payment because it exceeds its budget line', function () {
    $huge = payment('400.00');

    expect(app(PostVoucher::class)->check($huge))->toBe([]);
});
