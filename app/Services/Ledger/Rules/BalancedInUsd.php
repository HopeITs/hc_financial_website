<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;

/**
 * Rule 4: a voucher balances exactly in USD, to the cent. The cents left by rounding are moved to the exchange
 * differences account before this rule looks (see RoundingLine); anything bigger is a mistake.
 */
final class BalancedInUsd implements PostingRule
{
    public function number(): int
    {
        return 4;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        if (count($draft->lines) < 2) {
            return [new Violation($this->number(), __('ledger.rules.balanced_lines'))];
        }

        $difference = self::difference($draft);

        if (bccomp($difference, '0', 2) !== 0) {
            return [new Violation($this->number(), __('ledger.rules.balanced_usd', ['difference' => Money::abs($difference)]))];
        }

        return [];
    }

    /**
     * Debits minus credits in USD; positive when the debits are bigger.
     *
     * @return numeric-string
     */
    public static function difference(VoucherDraft $draft): string
    {
        $difference = Money::zero();

        foreach ($draft->lines as $line) {
            $difference = bcsub(bcadd($difference, $line->debitUsd, 2), $line->creditUsd, 2);
        }

        return $difference;
    }
}
