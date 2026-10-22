<?php

namespace App\Services\Ledger\Rules;

use App\Models\Voucher;
use App\Services\Ledger\Violation;

/** Rule 7: the person who prepared a voucher never approves it, whatever their role. */
final class ApproverIsNotPreparer
{
    public function number(): int
    {
        return 7;
    }

    /**
     * @param  int  $approverId  the legacy `user`.`ID` of the person approving
     * @return list<Violation>
     */
    public function check(Voucher $voucher, int $approverId): array
    {
        if ($voucher->prepared_by === $approverId) {
            return [new Violation($this->number(), __('ledger.rules.approver_not_preparer'))];
        }

        return [];
    }
}
