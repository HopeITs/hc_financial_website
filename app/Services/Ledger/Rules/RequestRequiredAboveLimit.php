<?php

namespace App\Services\Ledger\Rules;

use App\Enums\VoucherSource;
use App\Enums\VoucherType;
use App\Models\LedgerRequest;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/**
 * Financial Manager decision (plan 6.9): a payment not linked to an approved request is refused above USD 500 per
 * voucher. The amount is the voucher's USD total, so an SYP voucher is compared at its own voucher rate.
 * Migrated vouchers are history and are not held to it. The budget line limit is not a rule (warning only).
 */
final class RequestRequiredAboveLimit implements PostingRule
{
    public function number(): int
    {
        return 14;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        if ($draft->type !== VoucherType::Payment || $draft->source === VoucherSource::Migration) {
            return [];
        }

        if ($draft->requestId !== null) {
            $request = LedgerRequest::query()->find($draft->requestId);

            return $request?->status === LedgerRequest::APPROVED
                ? []
                : [new Violation($this->number(), __('ledger.rules.request_not_approved', ['request' => $draft->requestId]))];
        }

        $configured = config('ledger.request_limit_usd');
        $limit = is_numeric($configured) ? bcadd((string) $configured, '0', 2) : '0.00';
        $total = '0.00';

        foreach ($draft->lines as $line) {
            $total = bcadd($total, $line->debitUsd, 2);
        }

        return bccomp($total, $limit, 2) > 0
            ? [new Violation($this->number(), __('ledger.rules.request_required', ['amount' => $total, 'limit' => $limit]))]
            : [];
    }
}
