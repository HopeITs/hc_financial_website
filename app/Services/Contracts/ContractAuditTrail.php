<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractAudit;
use App\Models\GlBudgetLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Writes an agreement's audit trail (`gl_contract_audit`): budget lines added, removed or imported, and the agreement
 * deleted. Each entry says whether the agreement was already approved, since a budget may still change after approval.
 */
class ContractAuditTrail
{
    /** @param  string  $source  how the line came in: `form` (typed) or `excel` (an import) */
    public function lineAdded(Contract $contract, GlBudgetLine $line, User $by, string $source = 'form'): void
    {
        $this->record($contract, ContractAudit::BUDGET_LINE_ADDED, $by, $line->project_code, $line->key, $this->lineDetails($line) + ['source' => $source]);
    }

    public function lineRemoved(Contract $contract, GlBudgetLine $line, User $by): void
    {
        $this->record($contract, ContractAudit::BUDGET_LINE_REMOVED, $by, $line->project_code, $line->key, $this->lineDetails($line));
    }

    /**
     * @param  list<array{name: string, account: string, amount: string}>  $lines
     */
    public function budgetImported(Contract $contract, string $projectCode, array $lines, User $by): void
    {
        $total = array_reduce($lines, fn (string $sum, array $line): string => bcadd($sum, (string) $line['amount'], 2), '0.00');

        $this->record($contract, ContractAudit::BUDGET_IMPORTED, $by, $projectCode, null, [
            'lines' => count($lines), 'total' => $total, 'currency' => $contract->currency,
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function agreementDeleted(Contract $contract, User $by, array $details): void
    {
        $this->record($contract, ContractAudit::AGREEMENT_DELETED, $by, null, null, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(Contract $contract, string $action, User $by, ?string $projectCode, ?string $lineKey, array $details): void
    {
        ContractAudit::query()->create([
            'contract_id' => $contract->id,
            'contract_number' => $contract->number,
            'action' => $action,
            'project_code' => $projectCode,
            'budget_line_key' => $lineKey,
            'details' => $details,
            'after_approval' => $contract->isApproved(),
            'changed_by' => (int) $by->getKey(),
        ]);
    }

    /**
     * The latest entries of one agreement's trail, newest first.
     *
     * @return Collection<int, ContractAudit>
     */
    public function latest(Contract $contract, int $limit = 50): Collection
    {
        return ContractAudit::query()->where('contract_id', $contract->id)->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }

    /** @return array{name: string, account: string, amount: string, currency: string} */
    private function lineDetails(GlBudgetLine $line): array
    {
        return ['name' => $line->name, 'account' => $line->account_code, 'amount' => (string) $line->budget, 'currency' => $line->currency];
    }
}
