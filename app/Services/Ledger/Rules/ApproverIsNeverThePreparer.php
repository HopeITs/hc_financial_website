<?php

namespace App\Services\Ledger\Rules;

use App\Models\Voucher;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 7: whoever prepared a voucher can never approve it. (The role and limit checks live in `ApprovalPolicy`.) */
class ApproverIsNeverThePreparer
{
    public function check(Voucher $voucher, int $approverId): void
    {
        if ($voucher->prepared_by === $approverId) {
            throw PostingRuleViolation::of('approver_is_preparer');
        }
    }
}
