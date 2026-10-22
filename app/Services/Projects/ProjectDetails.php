<?php

namespace App\Services\Projects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The tabs of the project page that are not balances: budget lines and the lifetime of income and expenses.
 * The balances themselves come from `ProjectBalanceSource`.
 */
class ProjectDetails
{
    /** `action`.`AType_ID` of an income and of an expense; to be checked against the Windows program's lifetime report. */
    public const INCOME = 1;

    public const EXPENSE = 4;

    /**
     * Budget lines of the project's budgets. `total` is Unit x Timing x Price, as a two-decimal string.
     *
     * @return Collection<int, array{budget: string, line: string, unit: string, timing: string, price: string, total: string}>
     */
    public function budgetLines(int $accountId): Collection
    {
        return DB::connection('legacy')->table('budget_line')
            ->join('budget', 'budget.ID', '=', 'budget_line.Budget_ID')
            ->leftJoin('line', 'line.ID', '=', 'budget_line.Line_ID')
            ->where('budget.Account_ID', $accountId)
            ->orderBy('budget.Name')->orderBy('line.Name')
            ->get(['budget.Name as budget', 'line.Name as line', 'budget_line.Unit as unit', 'budget_line.Timing as timing', 'budget_line.Price as price'])
            ->map(fn (object $row): array => [
                'budget' => (string) $row->budget,
                'line' => (string) $row->line,
                'unit' => (string) ($row->unit ?? 0),
                'timing' => (string) ($row->timing ?? 0),
                'price' => $this->money($row->price),
                'total' => bcmul(bcmul($this->number($row->unit), $this->number($row->timing), 4), $this->number($row->price), 2),
            ])->values();
    }

    /**
     * Income and expenses per month and currency over the project's life, newest month first.
     * Deleted and unapproved actions are left out.
     *
     * @return Collection<int, array{month: string, currency: string, income: string, expense: string}>
     */
    public function lifetime(int $accountId): Collection
    {
        $connection = DB::connection('legacy');
        $month = $connection->getDriverName() === 'sqlite' ? "strftime('%Y-%m', action.ActionDate)" : "DATE_FORMAT(action.ActionDate, '%Y-%m')";

        return $connection->table('action')
            ->join('till', 'till.ID', '=', 'action.Till_ID')
            ->join('tilltypes', 'tilltypes.ID', '=', 'till.Type_ID')
            ->where('till.Account_ID', $accountId)
            ->whereNull('action.deleted_at')
            ->where('action.IsApproved', 1)
            ->whereIn('action.AType_ID', [self::INCOME, self::EXPENSE])
            ->selectRaw("$month as month, tilltypes.Name as currency,
                SUM(CASE WHEN action.AType_ID = ? THEN action.Amount ELSE 0 END) as income,
                SUM(CASE WHEN action.AType_ID = ? THEN action.Amount ELSE 0 END) as expense", [self::INCOME, self::EXPENSE])
            ->groupBy('month', 'currency')
            ->orderByDesc('month')->orderBy('currency')
            ->get()
            ->map(fn (object $row): array => [
                'month' => (string) $row->month,
                'currency' => (string) $row->currency,
                'income' => $this->money($row->income),
                'expense' => $this->money($row->expense),
            ])->values();
    }

    /**
     * @return numeric-string
     */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return numeric-string
     */
    private function number(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
