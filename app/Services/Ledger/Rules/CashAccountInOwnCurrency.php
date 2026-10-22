<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Models\GlAccount;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 2: a cash account takes only its own currency (USD cash box, SYP cash box). Other accounts are not limited here. */
class CashAccountInOwnCurrency implements PostingRule
{
    public function check(VoucherDraft $draft): void
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($l) => $l->string('account_code'), $draft->lines))));
        $accounts = GlAccount::query()->whereIn('code', $codes)->get()->keyBy('code');

        foreach ($draft->lines as $line) {
            $account = $accounts->get($line->string('account_code') ?? '');

            if ($account === null || ! $account->is_cash || in_array($account->currency, [null, 'Any'], true)) {
                continue;
            }

            if ($line->string('currency') !== $account->currency) {
                throw PostingRuleViolation::of('cash_currency', [
                    'account' => $account->code, 'expected' => $account->currency, 'currency' => $line->string('currency') ?? '',
                ]);
            }
        }
    }
}
