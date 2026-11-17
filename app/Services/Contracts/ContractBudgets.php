<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\GlBudgetLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The budget of each project of an agreement (finance decision of 2026-11-17): its budget lines, each tied to one
 * expense account, in the agreement's currency. They are the ledger's own budget lines (`gl_budget_lines`), so entries
 * and requests can post to them. A line that an entry, a request or a payroll split already uses is never removed.
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
