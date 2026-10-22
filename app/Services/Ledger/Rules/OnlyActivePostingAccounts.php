<?php

namespace App\Services\Ledger\Rules;

use App\Enums\AccountLevel;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/** Rule 1: a line can only post to an account that exists, is active and is a posting account (not a header). */
final class OnlyActivePostingAccounts implements PostingRule
{
    public function number(): int
    {
        return 1;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        $violations = [];

        foreach ($draft->lines as $index => $line) {
            $account = $context->account($line->accountCode);

            if ($account === null || ! $account->is_active || $account->level !== AccountLevel::Posting) {
                $violations[] = new Violation($this->number(), __('ledger.rules.account_unusable', ['account' => $line->accountCode]), $index + 1);
            }
        }

        return $violations;
    }
}
