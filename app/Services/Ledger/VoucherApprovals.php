<?php

namespace App\Services\Ledger;

use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Ledger\Rules\ApproverIsNotPreparer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Approving and rejecting a pending voucher. Approving needs the approve action on the vouchers screen and the
 * role's limit (the approval policy), never the person who prepared it (rule 7), and every posting rule must still
 * hold, including an open month (rule 8). Rejecting needs the approve action too and a reason; the voucher keeps its
 * number and is soft deleted, with the reason in its audit trail. Workflow breaks are reported as rule 0.
 */
class VoucherApprovals
{
    public function __construct(private readonly PostVoucher $posting) {}

    /** @throws LedgerRuleException */
    public function approve(Voucher $voucher, User $approver): Voucher
    {
        return DB::transaction(function () use ($voucher, $approver) {
            $voucher = Voucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();
            $approverId = (int) $approver->getAuthIdentifier();
            $violations = [];

            if ($voucher->status !== VoucherStatus::PendingApproval) {
                $violations[] = new Violation(0, __('ledger.rules.not_pending'));
            } else {
                array_push($violations, ...(new ApproverIsNotPreparer)->check($voucher, $approverId));

                if ($violations === [] && ! Gate::forUser($approver)->allows('approve', $voucher)) {
                    $violations[] = new Violation(0, __('ledger.rules.approval_not_allowed'));
                }

                if ($violations === []) {
                    array_push($violations, ...$this->posting->recheck($voucher));
                }
            }

            if ($violations !== []) {
                throw new LedgerRuleException($violations);
            }

            $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => $approverId, 'approved_at' => now()]);
            VoucherAudit::query()->create(['voucher_id' => $voucher->id, 'action' => 'approve', 'changed_by' => $approverId]);

            return $voucher->refresh();
        });
    }

    /** @throws LedgerRuleException */
    public function reject(Voucher $voucher, User $rejector, string $reason): void
    {
        DB::transaction(function () use ($voucher, $rejector, $reason) {
            $voucher = Voucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();
            $rejectorId = (int) $rejector->getAuthIdentifier();
            $violations = [];

            if ($voucher->status !== VoucherStatus::PendingApproval) {
                $violations[] = new Violation(0, __('ledger.rules.not_pending'));
            } elseif (! $rejector->canOnScreen('vouchers', ScreenAction::Approve)) {
                $violations[] = new Violation(0, __('ledger.rules.approval_not_allowed'));
            } elseif ($voucher->prepared_by === $rejectorId) {
                $violations[] = new Violation(0, __('ledger.rules.reject_own'));
            }

            if (trim($reason) === '') {
                $violations[] = new Violation(0, __('ledger.rules.reject_reason'));
            }

            if ($violations !== []) {
                throw new LedgerRuleException($violations);
            }

            VoucherAudit::query()->create(['voucher_id' => $voucher->id, 'action' => 'reject', 'changed_by' => $rejectorId, 'reason' => trim($reason)]);
            $voucher->delete();
        });
    }
}
