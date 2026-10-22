<?php

namespace App\Services\Ledger\Rules;

use App\Models\Voucher;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 10: a voucher number is given once. Rejected (soft deleted) vouchers keep theirs, so they count too. */
class NumbersAreNeverReused
{
    public function check(string $number): void
    {
        if (Voucher::withTrashed()->whereKey($number)->exists()) {
            throw PostingRuleViolation::of('number_reused', ['number' => $number]);
        }
    }
}
