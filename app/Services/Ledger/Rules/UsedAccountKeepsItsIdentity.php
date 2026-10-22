<?php

namespace App\Services\Ledger\Rules;

use App\Enums\AccountClass;
use App\Models\GlAccount;
use App\Models\VoucherLine;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 11: once a voucher line uses an account, its code, class and currency never change (it can only be deactivated). */
class UsedAccountKeepsItsIdentity
{
    /**
     * @param  array<string, mixed>  $changes  the new values by column (`code`, `class`, `currency`, ...)
     */
    public function check(GlAccount $account, array $changes): void
    {
        if (! VoucherLine::query()->where('account_code', $account->code)->exists()) {
            return;
        }

        $current = ['code' => $account->code, 'class' => $account->class, 'currency' => $account->currency];

        foreach ($current as $field => $value) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            $old = $value instanceof AccountClass ? $value->value : $value;
            $new = $changes[$field] instanceof AccountClass ? $changes[$field]->value : $changes[$field];

            if ($new !== $old) {
                throw PostingRuleViolation::of('used_account_locked', ['account' => $account->code, 'field' => $field]);
            }
        }
    }
}
