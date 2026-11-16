<?php

namespace App\Services\Contracts;

use App\Enums\ContractClosingOutcome;
use App\Enums\ContractStatus;
use App\Enums\RoleId;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The rules of a funding contract (finance design of 2026-11-16):
 *
 * - It is approved by the Financial Manager, and only when its project shares and its instalments each add up to the
 *   contract amount. Until then it is not used in entries or reports.
 * - Once approved, its amount, currency, rate, project shares and instalment amounts are fixed.
 * - Closing records what happens to the unspent balance with the donor's written approval, then needs the Financial
 *   Manager and, after them, the General Manager. The second approval ends the contract.
 */
class ContractWorkflow
{
    /**
     * What stops the contract from being approved, one message each; empty when it can be approved.
     *
     * @return list<string>
     */
    public function problemsBeforeApproval(Contract $contract): array
    {
        $problems = [];
        $amount = (string) $contract->amount;
        $shares = $this->sum($contract->projects()->pluck('amount')->all());
        $instalments = $this->sum($contract->instalments()->pluck('amount')->all());

        if (! $contract->projects()->exists()) {
            $problems[] = __('contracts.problem_no_projects');
        } elseif (bccomp($shares, $amount, 2) !== 0) {
            $problems[] = __('contracts.problem_shares', ['total' => $shares, 'amount' => $amount]);
        }

        if (! $contract->instalments()->exists()) {
            $problems[] = __('contracts.problem_no_instalments');
        } elseif (bccomp($instalments, $amount, 2) !== 0) {
            $problems[] = __('contracts.problem_instalments', ['total' => $instalments, 'amount' => $amount]);
        }

        return $problems;
    }

    public function approve(Contract $contract, User $approver): void
    {
        if ($contract->isApproved() || ! $contract->isOpen()) {
            throw new ContractException([__('contracts.problem_not_approvable')]);
        }

        if (! $this->isFinancialManager($approver)) {
            throw new ContractException([__('contracts.problem_fm_only')]);
        }

        $problems = $this->problemsBeforeApproval($contract);

        if ($problems !== []) {
            throw new ContractException($problems);
        }

        $contract->update([
            'approved_by' => $approver->getKey(),
            'approved_at' => Carbon::now(),
            'status' => $contract->status === ContractStatus::Draft ? ContractStatus::Signed->value : $contract->status->value,
        ]);
    }

    public function requestClosing(Contract $contract, ContractClosingOutcome $outcome, string $note, string $filePath, string $fileName, User $requester): void
    {
        if (! $contract->isApproved() || ! $contract->isOpen() || $contract->isClosingRequested()) {
            throw new ContractException([__('contracts.problem_not_closable')]);
        }

        $contract->update([
            'closing_outcome' => $outcome->value,
            'closing_note' => $note,
            'closing_file_path' => $filePath,
            'closing_file_name' => $fileName,
            'closing_requested_by' => $requester->getKey(),
            'closing_requested_at' => Carbon::now(),
            'closing_fm_by' => null,
            'closing_fm_at' => null,
            'closing_gm_by' => null,
            'closing_gm_at' => null,
        ]);
    }

    /** The closing step still to be done: the Financial Manager first, then the General Manager; null when none is due. */
    public function nextClosingStep(Contract $contract): ?RoleId
    {
        if (! $contract->isClosingRequested()) {
            return null;
        }

        return $contract->closing_fm_at === null ? RoleId::FinancialManager : RoleId::GeneralManager;
    }

    public function canApproveClosing(Contract $contract, User $user): bool
    {
        return match ($this->nextClosingStep($contract)) {
            RoleId::FinancialManager => $this->isFinancialManager($user),
            RoleId::GeneralManager => $user->isSuperUser() || in_array(RoleId::GeneralManager, $user->roleIds(), true),
            default => false,
        };
    }

    public function approveClosing(Contract $contract, User $approver): void
    {
        $step = $this->nextClosingStep($contract);

        if ($step === null || ! $this->canApproveClosing($contract, $approver)) {
            throw new ContractException([__('contracts.problem_closing_step')]);
        }

        if ($step === RoleId::FinancialManager) {
            $contract->update(['closing_fm_by' => $approver->getKey(), 'closing_fm_at' => Carbon::now()]);

            return;
        }

        $contract->update([
            'closing_gm_by' => $approver->getKey(),
            'closing_gm_at' => Carbon::now(),
            'status' => ContractStatus::Ended->value,
        ]);
    }

    private function isFinancialManager(User $user): bool
    {
        return $user->isSuperUser() || in_array(RoleId::FinancialManager, $user->roleIds(), true);
    }

    /**
     * @param  array<int, mixed>  $amounts
     * @return numeric-string
     */
    private function sum(array $amounts): string
    {
        return array_reduce($amounts, fn (string $total, mixed $amount): string => bcadd($total, (string) $amount, 2), '0.00');
    }
}
