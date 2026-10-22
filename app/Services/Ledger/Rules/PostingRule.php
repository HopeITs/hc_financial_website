<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Services\Ledger\PostingRuleViolation;

/**
 * A rule that every new voucher must pass before it is saved.
 */
interface PostingRule
{
    /**
     * @throws PostingRuleViolation
     */
    public function check(VoucherDraft $draft): void;
}
