<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Enums\VoucherType;
use App\Models\LedgerRequest;
use App\Services\Ledger\PostingRuleViolation;

/**
 * Financial Manager decision (plan 6.9): a payment not linked to an approved request is refused above USD 500 per
 * voucher. The amount is the voucher's USD total, so an SYP voucher is compared at its own voucher rate.
 * Imported (migrated) vouchers are history and are not held to it.
 */
class RequestRequiredAboveLimit implements PostingRule
{
    public function check(VoucherDraft $draft): void
    {
        if ($draft->type !== VoucherType::Payment || $draft->source === 'migration') {
            return;
        }

        if ($draft->requestId !== null) {
            $request = LedgerRequest::query()->find($draft->requestId);

            if ($request === null || $request->status !== LedgerRequest::APPROVED) {
                throw PostingRuleViolation::of('request_not_approved', ['request' => $draft->requestId]);
            }

            return;
        }

        $configured = config('finance.request_limit_usd');
        $limit = is_numeric($configured) ? bcadd((string) $configured, '0', 2) : '0.00';
        $total = $draft->totalDebitUsd();

        if (bccomp($total, $limit, 2) > 0) {
            throw PostingRuleViolation::of('request_required', ['amount' => $total, 'limit' => $limit]);
        }
    }
}
