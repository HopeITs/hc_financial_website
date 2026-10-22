<?php

namespace App\Policies;

use App\Contracts\Approvable;
use App\Enums\ScreenAction;
use App\Models\User;
use App\Services\Approvals\ApprovalLimits;

/**
 * Who may approve a voucher: a role with the approve action on the vouchers screen,
 * never the person who prepared it, and only within the role's limit in that currency.
 */
class ApprovalPolicy
{
    public function __construct(private readonly ApprovalLimits $limits) {}

    public function approve(User $user, Approvable $subject): bool
    {
        if ($subject->preparedBy() === (int) $user->getAuthIdentifier()) {
            return false;
        }

        if (! $user->canOnScreen('vouchers', ScreenAction::Approve)) {
            return false;
        }

        foreach ($user->roleIds() as $role) {
            if ($role->isWebOnly()) {
                continue;
            }

            if ($this->limits->allows($role, $subject->approvalCurrencyId(), $subject->approvalAmount())) {
                return true;
            }
        }

        return false;
    }
}
