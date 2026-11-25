<?php

namespace App\Services\Ledger;

use App\Models\BudgetLineNote;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\MicroPurchase;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The monthly financial report of a project, budget against actual in dollars (finance form "Hope Center – Monthly
 * Financial Report", 2026-11-28), from approved vouchers instead of a hand-kept register:
 *
 * - the project, the reporting month, the months elapsed and the share of the project's time gone;
 * - the key figures: total budget, spent this month, spent to date, remaining, burn rate and time elapsed;
 * - A. the summary by budget category and B. the detail by budget line: budget, previous months, this month, to date,
 *   remaining, burn rate, status and the explanation written for the month;
 * - the monthly breakdown: each line's dollars month by month since the project began;
 * - the micro-project beneficiaries: each one's invoices in pounds and in dollars.
 *
 * The status of a line or category: ✖ over (spent more than its budget), ✖ unbudgeted (spent on a zero budget),
 * ⚠ near (burn rate at 90 % or more), ▽ slow (burn rate 25 points or more behind the time elapsed), ✔ on track.
 * Over, unbudgeted and near need an explanation.
 *
 * @phpstan-type Row array{budget: numeric-string, previous: numeric-string, month: numeric-string, to_date: numeric-string, remaining: numeric-string, burn: numeric-string|null, status: string}
 */
class MonthlyProjectReport
{
    /** A line at this share of its budget is near its limit. */
    public const NEAR_LIMIT = '0.90';

    /** A line this far behind the time elapsed is spending slowly. */
    public const SLOW_BEHIND = '0.25';

    /** The most months the breakdown shows, as the form does. */
    public const MAX_MONTHS = 60;

    /** The statuses that need an explanation before the report is sent. */
    public const NEEDS_EXPLANATION = ['over', 'unbudgeted', 'near'];

    /**
     * @return array{
     *     month: CarbonImmutable, from: CarbonImmutable, to: CarbonImmutable,
     *     months_total: int|null, months_elapsed: int|null, time: numeric-string|null,
     *     totals: Row,
     *     categories: list<Row&array{category: string}>,
     *     lines: list<Row&array{key: string, name: string, category: string, note: string}>,
     *     to_explain: int
     * }
     */
    public function build(GlProject $project, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        [$monthsTotal, $monthsElapsed, $time] = $this->time($project, $from);

        $sums = fn (?CarbonImmutable $start, CarbonImmutable $end) => $this->spending($project->code)
            ->when($start !== null, fn (Builder $query) => $query->whereDate('v.date', '>=', $start?->toDateString()))
            ->whereDate('v.date', '<=', $end->toDateString())
            ->groupBy('l.budget_line_key')
            ->selectRaw('l.budget_line_key as line_key, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->pluck('usd', 'line_key');
        $before = $sums(null, $from->subDay());
        $during = $sums($from, $to);
        $notes = $this->notes($project, $from, $to);

        $lines = [];
        $groups = [];
        foreach (GlBudgetLine::query()->where('project_code', $project->code)->orderBy('key')->get() as $line) {
            $category = trim((string) $line->getAttribute('category'));
            $category = $category !== '' ? $category : DonorFinancialReports::NO_CATEGORY;
            $row = $this->row($this->budgetUsd($line, $project), Money::normalize($before[$line->key] ?? '0'), Money::normalize($during[$line->key] ?? '0'), $time);
            $lines[] = ['key' => (string) $line->key, 'name' => (string) $line->name, 'category' => $category, 'note' => $notes[$line->key] ?? ''] + $row;

            $group = $groups[$category] ?? ['budget' => Money::zero(), 'previous' => Money::zero(), 'month' => Money::zero()];
            $groups[$category] = ['budget' => bcadd($group['budget'], $row['budget'], 2), 'previous' => bcadd($group['previous'], $row['previous'], 2), 'month' => bcadd($group['month'], $row['month'], 2)];
        }

        $categories = [];
        $total = ['budget' => Money::zero(), 'previous' => Money::zero(), 'month' => Money::zero()];
        foreach ($groups as $category => $group) {
            $categories[] = ['category' => (string) $category] + $this->row($group['budget'], $group['previous'], $group['month'], $time);
            $total = ['budget' => bcadd($total['budget'], $group['budget'], 2), 'previous' => bcadd($total['previous'], $group['previous'], 2), 'month' => bcadd($total['month'], $group['month'], 2)];
        }

        return [
            'month' => $from, 'from' => $from, 'to' => $to,
            'months_total' => $monthsTotal, 'months_elapsed' => $monthsElapsed, 'time' => $time,
            'totals' => $this->row($total['budget'], $total['previous'], $total['month'], $time),
            'categories' => $categories,
            'lines' => $lines,
            'to_explain' => count(array_filter($lines, fn (array $line): bool => in_array($line['status'], self::NEEDS_EXPLANATION, true) && trim($line['note']) === '')),
        ];
    }

    /**
     * Each budget line's dollars month by month, from the project's first month (or its first spending) to the
     * reporting month, at most the last 60 months.
     *
     * @return array{months: list<string>, rows: list<array{key: string, name: string, category: string, budget: numeric-string, spent: numeric-string, remaining: numeric-string, burn: numeric-string|null, by_month: array<string, numeric-string>}>, totals: array<string, numeric-string>}
     */
    public function breakdown(GlProject $project, CarbonImmutable $month): array
    {
        $to = $month->endOfMonth();
        $first = $project->start_date !== null ? CarbonImmutable::parse($project->start_date)->startOfMonth() : null;
        $firstSpending = $this->spending($project->code)->min('v.date');
        if ($first === null || ($firstSpending !== null && CarbonImmutable::parse(substr((string) $firstSpending, 0, 10))->lt($first))) {
            $first = $firstSpending !== null ? CarbonImmutable::parse(substr((string) $firstSpending, 0, 10))->startOfMonth() : $month->startOfMonth();
        }
        if ($first->gt($month->startOfMonth())) {
            $first = $month->startOfMonth();
        }

        $months = [];
        for ($day = $first; $day->lte($to) ; $day = $day->addMonthNoOverflow()) {
            $months[] = $day->format('Y-m');
        }
        $months = array_slice($months, -self::MAX_MONTHS);

        $sums = $this->spending($project->code)
            ->whereDate('v.date', '>=', $months[0].'-01')->whereDate('v.date', '<=', $to->toDateString())
            ->get(['l.budget_line_key', 'v.date', 'l.debit_usd', 'l.credit_usd']);
        $byLine = [];
        foreach ($sums as $sum) {
            $key = substr((string) $sum->date, 0, 7);
            $byLine[$sum->budget_line_key][$key] = bcadd($byLine[$sum->budget_line_key][$key] ?? Money::zero(), bcsub(Money::normalize($sum->debit_usd), Money::normalize($sum->credit_usd), 2), 2);
        }
        $spentToDate = $this->spending($project->code)->whereDate('v.date', '<=', $to->toDateString())
            ->groupBy('l.budget_line_key')->selectRaw('l.budget_line_key as line_key, sum(l.debit_usd) - sum(l.credit_usd) as usd')->pluck('usd', 'line_key');

        $rows = [];
        $totals = array_fill_keys($months, Money::zero());
        foreach (GlBudgetLine::query()->where('project_code', $project->code)->orderBy('key')->get() as $line) {
            $budget = $this->budgetUsd($line, $project);
            $spent = Money::normalize($spentToDate[$line->key] ?? '0');
            $byMonth = [];
            foreach ($months as $key) {
                $byMonth[$key] = $byLine[$line->key][$key] ?? Money::zero();
                $totals[$key] = bcadd($totals[$key], $byMonth[$key], 2);
            }
            $category = trim((string) $line->getAttribute('category'));
            $rows[] = [
                'key' => (string) $line->key, 'name' => (string) $line->name, 'category' => $category !== '' ? $category : DonorFinancialReports::NO_CATEGORY,
                'budget' => $budget, 'spent' => $spent, 'remaining' => bcsub($budget, $spent, 2), 'burn' => $this->share($spent, $budget), 'by_month' => $byMonth,
            ];
        }

        return ['months' => $months, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * The project's micro-project beneficiaries with the total of their invoices to the end of the month, in pounds as
     * paid and in dollars.
     *
     * @return list<array{number: string, name: string, syp: numeric-string, usd: numeric-string}>
     */
    public function beneficiaries(GlProject $project, CarbonImmutable $month): array
    {
        if (! Schema::hasTable('gl_micro_purchases')) {
            return [];
        }

        return DB::table('gl_micro_purchases as p')
            ->join('gl_micro_loans as m', 'm.id', '=', 'p.loan_id')
            ->where('m.project_code', $project->code)
            ->where('p.kind', MicroPurchase::INVOICE)
            ->whereDate('p.purchase_date', '<=', $month->endOfMonth()->toDateString())
            ->groupBy('m.id', 'm.project_number', 'm.beneficiary')
            ->orderBy('m.beneficiary')
            ->selectRaw("m.project_number, m.beneficiary, sum(case when p.currency = 'SYP' then p.amount else 0 end) as syp, sum(p.usd) as usd")
            ->get()
            ->map(fn (object $row): array => ['number' => (string) $row->project_number, 'name' => (string) $row->beneficiary, 'syp' => Money::normalize($row->syp), 'usd' => Money::normalize($row->usd)])
            ->all();
    }

    /**
     * A line's or a group's figures and status.
     *
     * @param  numeric-string  $budget
     * @param  numeric-string  $previous
     * @param  numeric-string  $month
     * @param  numeric-string|null  $time
     * @return Row
     */
    private function row(string $budget, string $previous, string $month, ?string $time): array
    {
        $toDate = bcadd($previous, $month, 2);
        $burn = $this->share($toDate, $budget);

        return [
            'budget' => $budget, 'previous' => $previous, 'month' => $month, 'to_date' => $toDate,
            'remaining' => bcsub($budget, $toDate, 2), 'burn' => $burn, 'status' => $this->status($budget, $toDate, $burn, $time),
        ];
    }

    /**
     * @param  numeric-string  $budget
     * @param  numeric-string  $toDate
     * @param  numeric-string|null  $burn
     * @param  numeric-string|null  $time
     */
    private function status(string $budget, string $toDate, ?string $burn, ?string $time): string
    {
        if (bccomp($budget, '0', 2) === 0) {
            return bccomp($toDate, '0', 2) > 0 ? 'unbudgeted' : 'none';
        }

        if (bccomp($toDate, $budget, 2) > 0) {
            return 'over';
        }

        if ($burn !== null && bccomp($burn, self::NEAR_LIMIT, 4) >= 0) {
            return 'near';
        }

        if ($burn !== null && $time !== null && bccomp(bcsub($time, $burn, 4), self::SLOW_BEHIND, 4) >= 0) {
            return 'slow';
        }

        return 'ok';
    }

    /**
     * The project's months, the months gone to the end of the reporting month, and that share; null without dates.
     *
     * @return array{0: int|null, 1: int|null, 2: numeric-string|null}
     */
    private function time(GlProject $project, CarbonImmutable $from): array
    {
        if ($project->start_date === null || $project->end_date === null) {
            return [null, null, null];
        }

        $start = CarbonImmutable::parse($project->start_date)->startOfMonth();
        $end = CarbonImmutable::parse($project->end_date)->startOfMonth();
        $total = ($end->year - $start->year) * 12 + $end->month - $start->month + 1;
        $elapsed = ($from->year - $start->year) * 12 + $from->month - $start->month + 1;

        if ($total <= 0) {
            return [null, null, null];
        }

        $share = bcdiv((string) max(0, min($total, $elapsed)), (string) $total, 4);

        return [$total, max(0, $elapsed), $share];
    }

    /** @return numeric-string the line's budget in dollars: a dollar line as it is, a donor-currency line at the project's donor rate */
    private function budgetUsd(GlBudgetLine $line, GlProject $project): string
    {
        $budget = Money::normalize($line->budget);

        if ($line->currency === Currencies::BASE) {
            return $budget;
        }

        $rate = (string) $project->getAttribute('donor_rate');

        if ($line->currency === $project->donor_currency && is_numeric($rate) && bccomp($rate, '0', 6) > 0) {
            return Money::normalize(bcmul($budget, $rate, 4));
        }

        return Money::zero();
    }

    /**
     * @param  numeric-string  $part
     * @param  numeric-string  $whole
     * @return numeric-string|null the share as a fraction (0.4512), or null on a zero budget
     */
    private function share(string $part, string $whole): ?string
    {
        return bccomp($whole, '0', 2) === 0 ? null : bcdiv($part, $whole, 4);
    }

    /** @return array<string, string> budget line key => the explanation written for the month */
    private function notes(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Schema::hasTable('gl_budget_line_notes')) {
            return [];
        }

        return BudgetLineNote::query()
            ->whereIn('budget_line_key', GlBudgetLine::query()->where('project_code', $project->code)->select('key'))
            ->whereDate('period_from', $from->toDateString())->whereDate('period_to', $to->toDateString())
            ->pluck('note', 'budget_line_key')->map(fn ($note): string => (string) $note)->all();
    }

    /** The approved lines of the project's budget lines. */
    private function spending(string $projectCode): Builder
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')->whereNull('v.deleted_at')
            ->where('l.project_code', $projectCode)->whereNotNull('l.budget_line_key');
    }
}
