<?php

namespace App\Services\Ledger;

use App\Services\Ledger\Rules\BalancedInUsd;
use App\Support\Money;

/**
 * Rule 4's other half: when a voucher is out by a few cents only (rounding of converted amounts), the cents go to
 * the exchange differences account on a line of their own. A bigger difference is left for the balance rule to refuse.
 */
final class RoundingLine
{
    public function balance(VoucherDraft $draft): VoucherDraft
    {
        if (count($draft->lines) < 2) {
            return $draft;
        }

        $difference = BalancedInUsd::difference($draft);
        $amount = Money::abs($difference);

        if (bccomp($difference, '0', 2) === 0 || bccomp($amount, Money::normalize(config('ledger.rounding_tolerance_usd')), 2) > 0) {
            return $draft;
        }

        $project = collect($draft->lines)->first(fn (LineDraft $line) => $line->projectCode !== null)?->projectCode;
        $debits = bccomp($difference, '0', 2) < 0;

        return $draft->withLines([...$draft->lines, new LineDraft(
            accountCode: (string) config('ledger.rounding_account'),
            currency: 'USD',
            debit: $debits ? $amount : null,
            credit: $debits ? null : $amount,
            projectCode: $project,
            reason: __('ledger.rounding_reason'),
        )]);
    }
}
