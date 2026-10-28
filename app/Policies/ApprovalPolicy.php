<?php

namespace App\Policies;

use App\Contracts\Approvable;
use App\Enums\ScreenAction;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Approvals\ApprovalChain;

/**
 * Who may approve a voucher: a role with the approve action on the vouchers screen,
 * never the person who prepared it. Above the General Manager threshold the General
 * Manager approves after the Financial Manager (two different people).
 */
class ApprovalPolicy
{
    public function __construct(private readonly ApprovalChain $chain) {}

    public function approve(User $user, Approvable $subject): bool
    {
        if ($subject->preparedBy() === (int) $user->getAuthIdentifier()) {
            return false;
        }

        if (! $user->canOnScreen('vouchers', ScreenAction::Approve)) {
            return false;
        }

        if ($subject instanceof Voucher && $subject->isDonorReceipt()) {
            return $this->mayApproveDonorReceipt($user, $subject);
        }

        foreach ($user->roleIds() as $role) {
            $actions = config("permissions.roles.{$role->value}.vouchers", []);

            if (! $role->isWebOnly() && in_array(ScreenAction::Approve->value, $actions, true)) {
                return true;
            }
        }

        return false;
    }

    private function mayApproveDonorReceipt(User $user, Voucher $voucher): bool
    {
        $id = (int) $user->getAuthIdentifier();

        if ($voucher->audits()->where('action', 'approve_step')->where('changed_by', $id)->exists()) {
            return false;
        }

        return $this->chain->mayApproveNext($voucher->approvalAmount(), $voucher->approvedSteps(), $user->roleIds());
    }
}
