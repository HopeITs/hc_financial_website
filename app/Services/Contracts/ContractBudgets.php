<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\GlBudgetLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The budget of each project of an agreement (finance decision of 2026-11-17): its budget lines, each tied to one
 * expense account, in the agreement's currency. They are the ledger's own budget lines (`gl_budget_lines`), so entries
 * and requests can post to them. A line that an entry, a request or a payroll split already uses is never removed.
 * The budget stays open to changes after approval, so that a budget approved without matching can be matched later.
 */
class ContractBudgets
{
    /** The tables whose rows point at a budget line. */
    private const USED_BY = ['gl_voucher_lines', 'gl_request_lines', 'gl_payroll_splits'];

    /** A budget line's key, as the ledger writes it: the project code and the line name. */
    public static function key(string $projectCode, string $name): string
    {
        return $projectCode.'|'.trim($name);
    }

    public function add(Contract $contract, string $projectCode, string $name, string $accountCode, string $amount): GlBudgetLine
    {
        $contract->loadMissing('donor');

        return GlBudgetLine::query()->create([
            'key' => self::key($projectCode, $name),
            'project_code' => $projectCode,
            'donor' => $contract->donor->name_en,
            'name' => trim($name),
            'account_code' => $accountCode,
            'budget' => $amount,
            'currency' => $contract->currency,
        ]);
    }

    /**
     * The projects of an agreement whose budget lines do not add up to their share. Approval is not blocked by it
     * (finance decision of 2026-11-17): the agreement is approved with a "needs budget matching" mark instead.
     *
     * @return list<array{project: string, total: string, share: string}>
     */
    public function mismatches(Contract $contract): array
    {
        $shares = $contract->projects()->get(['project_code', 'amount']);
        $totals = $this->totals($shares->pluck('project_code')->all());

        return array_values($shares
            ->map(fn (ContractProject $share): array => ['project' => $share->project_code, 'total' => $totals[$share->project_code] ?? '0.00', 'share' => (string) $share->amount])
            ->filter(fn (array $row): bool => bccomp($row['total'], $row['share'], 2) !== 0)
            ->all());
    }

    /**
     * Which of these agreements have a project whose budget does not add up to its share, in two queries.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return list<int>
     */
    public function mismatchedIds(Collection $contracts): array
    {
        $shares = ContractProject::query()->whereIn('contract_id', $contracts->pluck('id'))->get(['contract_id', 'project_code', 'amount']);
        $totals = $this->totals($shares->pluck('project_code')->unique()->all());

        return array_values($shares
            ->filter(fn (ContractProject $share): bool => bccomp($totals[$share->project_code] ?? '0.00', (string) $share->amount, 2) !== 0)
            ->pluck('contract_id')->unique()->map(fn ($id): int => (int) $id)->all());
    }

    /**
     * @param  list<string>  $projectCodes
     * @return array<string, string> project code => total of its budget lines
     */
    private function totals(array $projectCodes): array
    {
        return GlBudgetLine::query()->whereIn('project_code', $projectCodes)
            ->get(['project_code', 'budget'])
            ->groupBy('project_code')
            ->map(fn (Collection $lines): string => array_reduce($lines->pluck('budget')->all(), fn (string $sum, mixed $amount): string => bcadd($sum, (string) $amount, 2), '0.00'))
            ->all();
    }

    public function isUsed(GlBudgetLine $line): bool
    {
        foreach (self::USED_BY as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('budget_line_key', $line->key)->exists()) {
                return true;
            }
        }

        return false;
    }
}
