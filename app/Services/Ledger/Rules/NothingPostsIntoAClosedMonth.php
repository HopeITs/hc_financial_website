<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Models\MonthClose;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 8: a closed month takes no new voucher and no change to an existing one. */
class NothingPostsIntoAClosedMonth implements PostingRule
{
    public function check(VoucherDraft $draft): void
    {
        $this->checkMonth($draft->month());
    }

    /**
     * @param  string  $month  YYYY-MM
     */
    public function checkMonth(string $month): void
    {
        if (MonthClose::query()->whereKey($month)->exists()) {
            throw PostingRuleViolation::of('closed_month', ['month' => $month]);
        }
    }
}
