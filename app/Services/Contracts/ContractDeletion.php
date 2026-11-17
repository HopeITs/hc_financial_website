<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes an agreement (finance decision of 2026-11-17), with what was made for it: its shares, instalments, reports and
 * files, and the projects that belong to no other agreement together with their budget lines. A project that another
 * agreement also uses stays, with its budget.
 *
 * Nothing that the ledger already uses is ever deleted: when a voucher, a request, a salary split or a micro loan points
 * at one of those projects or budget lines, the agreement is not deleted and the reasons are given instead. The deletion
 * is written to the audit trail, which keeps it after the agreement is gone.
 */
class ContractDeletion
{
    /** Tables whose rows point at a project. */
    private const PROJECT_USERS = [
        'gl_voucher_lines' => 'vouchers',
        'gl_requests' => 'requests',
        'gl_payroll_splits' => 'salaries',
        'gl_micro_loans' => 'loans',
    ];

    /** Tables whose rows point at a budget line. */
    private const LINE_USERS = [
        'gl_voucher_lines' => 'vouchers',
        'gl_request_lines' => 'requests',
        'gl_payroll_splits' => 'salaries',
    ];

    public function __construct(
        private readonly ContractAuditTrail $trail,
        private readonly ContractFiles $files,
    ) {}

    /**
     * Why this agreement cannot be deleted; empty when it can.
     *
     * @return list<string>
     */
    public function blockers(Contract $contract): array
    {
        $problems = [];
        $projects = $this->ownProjects($contract);
        $lineKeys = GlBudgetLine::query()->whereIn('project_code', $projects)->pluck('key')->all();

        foreach (self::PROJECT_USERS as $table => $what) {
            $codes = $this->usedValues($table, 'project_code', $projects);
            if ($codes !== []) {
                $problems[] = __('contracts.delete_used.'.$what, ['projects' => implode(', ', $codes)]);
            }
        }

        foreach (self::LINE_USERS as $table => $what) {
            $keys = $this->usedValues($table, 'budget_line_key', $lineKeys);
            if ($keys !== []) {
                $problems[] = __('contracts.delete_used_lines.'.$what, ['lines' => implode(', ', $keys)]);
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Deletes the agreement and what belongs only to it, or refuses with the reasons.
     *
     * @throws ContractException when the ledger already uses one of its projects or budget lines
     */
    public function delete(Contract $contract, User $by): void
    {
        $problems = $this->blockers($contract);
        if ($problems !== []) {
            throw new ContractException($problems);
        }

        $contract->loadMissing('donor');
        $projects = $this->ownProjects($contract);
        $shared = array_values(array_diff($contract->projects()->pluck('project_code')->all(), $projects));
        $contractId = $contract->id;

        DB::transaction(function () use ($contract, $by, $projects, $shared): void {
            $lines = GlBudgetLine::query()->whereIn('project_code', $projects)->count();

            $this->trail->agreementDeleted($contract, $by, [
                'title' => $contract->title,
                'donor' => $contract->donor->name_en,
                'amount' => (string) $contract->amount,
                'currency' => $contract->currency,
                'status' => $contract->status->value,
                'projects_deleted' => $projects,
                'projects_kept' => $shared,
                'budget_lines_deleted' => $lines,
            ]);

            // The children go first, so nothing depends on the database cascading them.
            $contract->receipts()->delete();
            $contract->reports()->delete();
            $contract->instalments()->delete();
            $contract->projects()->delete();
            $contract->delete();
            GlBudgetLine::query()->whereIn('project_code', $projects)->delete();
            GlProject::query()->whereIn('code', $projects)->delete();
        });

        $this->files->forgetAll($contractId);
    }

    /**
     * The agreement's projects that no other agreement uses: they were made for it and go with it.
     *
     * @return list<string>
     */
    private function ownProjects(Contract $contract): array
    {
        $codes = $contract->projects()->pluck('project_code')->all();
        $elsewhere = ContractProject::query()->whereIn('project_code', $codes)->where('contract_id', '!=', $contract->id)->pluck('project_code')->all();

        return array_values(array_diff($codes, $elsewhere));
    }

    /**
     * @param  list<string>  $values
     * @return list<string> the values that rows of the table point at
     */
    private function usedValues(string $table, string $column, array $values): array
    {
        if ($values === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return array_values(array_map('strval', DB::table($table)->whereIn($column, $values)->distinct()->pluck($column)->all()));
    }
}
