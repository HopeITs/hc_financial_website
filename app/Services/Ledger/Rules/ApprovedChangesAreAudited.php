<?php

namespace App\Services\Ledger\Rules;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Services\Ledger\AmendVoucher;
use App\Services\Ledger\PostingRuleViolation;

/**
 * Rule 9: a change to an approved voucher needs a reason and a user; {@see AmendVoucher}
 * then keeps the old value, the new value, the user and the reason in `gl_voucher_audit`.
 */
class ApprovedChangesAreAudited
{
    public function check(Voucher $voucher, ?int $userId, ?string $reason): void
    {
        if ($voucher->status !== VoucherStatus::Approved) {
            return;
        }

        if ($userId === null || $reason === null || trim($reason) === '') {
            throw PostingRuleViolation::of('change_needs_reason', ['voucher' => $voucher->id]);
        }
    }
}
