<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Enums\AccountLevel;
use App\Models\GlAccount;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 1: a line posts only to an existing, active posting account, never to a header. */
class OnlyActivePostingAccounts implements PostingRule
{
    public function check(VoucherDraft $draft): void
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($l) => $l->string('account_code'), $draft->lines))));
        $accounts = GlAccount::query()->whereIn('code', $codes)->get()->keyBy('code');

        foreach ($draft->lines as $line) {
            $code = $line->string('account_code') ?? '';
            $account = $accounts->get($code);

            if ($account === null || $account->level !== AccountLevel::Posting || ! $account->is_active) {
                throw PostingRuleViolation::of('active_posting_account', ['account' => $code]);
            }
        }
    }
}
