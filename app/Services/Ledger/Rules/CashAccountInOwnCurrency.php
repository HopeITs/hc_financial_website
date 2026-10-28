<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\Currencies;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/**
 * Rule 2: a line is in a currency the books accept (the currencies table, R4-4), and an account fixed to one currency
 * (every cash account is) only takes lines in that currency.
 */
final class CashAccountInOwnCurrency implements PostingRule
{
    public function number(): int
    {
        return 2;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        $currencies = app(Currencies::class);
        $violations = [];

        foreach ($draft->lines as $index => $line) {
            if (! $currencies->isActive($line->currency)) {
                $violations[] = new Violation($this->number(), __('ledger.rules.currency_unsupported', ['currency' => $line->currency]), $index + 1);

                continue;
            }

            $account = $context->account($line->accountCode);

            if ($account !== null && $currencies->isActive((string) $account->currency) && $account->currency !== $line->currency) {
                $violations[] = new Violation($this->number(), __('ledger.rules.currency_fixed', [
                    'account' => $account->code, 'currency' => $account->currency, 'line_currency' => $line->currency,
                ]), $index + 1);
            }
        }

        return $violations;
    }
}
