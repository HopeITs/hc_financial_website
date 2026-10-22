<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/**
 * Rule 2: an account fixed to USD or SYP (every cash account is) only takes lines in that currency. The books
 * only know USD and SYP.
 */
final class CashAccountInOwnCurrency implements PostingRule
{
    private const CURRENCIES = ['USD', 'SYP'];

    public function number(): int
    {
        return 2;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        $violations = [];

        foreach ($draft->lines as $index => $line) {
            if (! in_array($line->currency, self::CURRENCIES, true)) {
                $violations[] = new Violation($this->number(), __('ledger.rules.currency_unsupported', ['currency' => $line->currency]), $index + 1);

                continue;
            }

            $account = $context->account($line->accountCode);

            if ($account !== null && in_array($account->currency, self::CURRENCIES, true) && $account->currency !== $line->currency) {
                $violations[] = new Violation($this->number(), __('ledger.rules.currency_fixed', [
                    'account' => $account->code, 'currency' => $account->currency, 'line_currency' => $line->currency,
                ]), $index + 1);
            }
        }

        return $violations;
    }
}
