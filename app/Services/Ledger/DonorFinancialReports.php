<?php

namespace App\Services\Ledger;

use App\Enums\AccountClass;
use App\Models\BudgetLineNote;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Services\Treasury\MonthlyRates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The donor's financial report of one project for a period (finance requests of 2026-11-26), from approved vouchers,
 * in the pages the donor receives:
 *
 * - the project's information, with what was received, spent and is left at the end of the period;
 * - the summary by budget category in the donor's currency, with the share spent of each category;
 * - the list of expenses: date, document type and number, description in English, the amount in pounds, dollars and
 *   the donor's currency with the rate of each, the budget line and its category, with the columns' totals;
 * - budget against actual by line: spent before, in and to the end of the period, remaining and share spent, with the
 *   reason the line went over or stayed under its budget, written for the period;
 * - the donor's funds statement: opening, received, spent and closing balance.
 *
 * A budget line counts its spending in its own currency as ProjectBalances::budgetVsActual does: a dollar line at the
 * dollar value, any other at the amount entered in its currency or recorded in the donor's. In the donor's currency a
 * line counts the donor amounts recorded on its vouchers (the dollars for a dollar donor), and its budget is converted
 * at the project's donor rate when it is in dollars.
 *
 * @phpstan-type BudgetLine array{key: string, name: string, category: string, account_code: string, currency: string, budget: numeric-string, before: numeric-string, period: numeric-string, to_date: numeric-string, remaining: numeric-string, percent: numeric-string, period_usd: numeric-string, status: string, note: string}
 * @phpstan-type Expense array{date: string, voucher: string, document_type: string, document_number: string, description: string, line: string, category: string, currency: string, syp: numeric-string|null, usd: numeric-string, donor: numeric-string|null, syp_rate: numeric-string|null, donor_rate: numeric-string|null, attachments: int}
 * @phpstan-type Receipt array{date: string, voucher: string, description: string, donor: numeric-string, usd: numeric-string}
 */
class DonorFinancialReports
{
    public const NO_CATEGORY = '—';

    public function __construct(private readonly MonthlyRates $monthly) {}

    /**
     * The project's page: its budget, and what was received, spent and is left at the end of the period, in the donor's
     * currency and in dollars.
     *
     * @return array{currency: string, budget: numeric-string, received: array{donor: numeric-string, usd: numeric-string}, spent: array{donor: numeric-string, usd: numeric-string}, balance: array{donor: numeric-string, usd: numeric-string}, percent: numeric-string, lines: int}
     */
    public function info(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $budget = Money::zero();
        $lines = GlBudgetLine::query()->where('project_code', $project->code)->get();

        foreach ($lines as $line) {
            $budget = bcadd($budget, $this->budgetInDonor($line, $project) ?? Money::zero(), 2);
        }

        $received = $this->received($project, null, $to);
        $spent = $this->spent($project, null, $to);

        return [
            'currency' => $project->donor_currency,
            'budget' => $budget,
            'received' => $received,
            'spent' => $spent,
            'balance' => ['donor' => bcsub($received['donor'], $spent['donor'], 2), 'usd' => bcsub($received['usd'], $spent['usd'], 2)],
            'percent' => $this->percent($spent['donor'], $budget),
            'lines' => $lines->count(),
        ];
    }

    /**
     * The summary by budget category, in the donor's currency: budget, spent in the period and to its end, remaining
     * and the share spent.
     *
     * @return array{currency: string, rows: list<array{category: string, budget: numeric-string, period: numeric-string, to_date: numeric-string, remaining: numeric-string, percent: numeric-string}>, total: array{budget: numeric-string, period: numeric-string, to_date: numeric-string, remaining: numeric-string, percent: numeric-string}}
     */
    public function categories(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $column = $project->donor_currency === Currencies::BASE ? 'usd' : 'donor';
        $sums = fn (?CarbonImmutable $start, CarbonImmutable $end) => $this->spending($project->code)
            ->when($start !== null, fn (Builder $query) => $query->whereDate('v.date', '>=', $start?->toDateString()))
            ->whereDate('v.date', '<=', $end->toDateString())
            ->groupBy('l.budget_line_key')
            ->selectRaw('l.budget_line_key as line_key, sum(l.debit_usd) - sum(l.credit_usd) as usd, sum(l.debit_donor) - sum(l.credit_donor) as donor')
            ->get()->keyBy('line_key');
        $toDate = $sums(null, $to);
        $during = $sums($from, $to);

        $groups = [];
        foreach (GlBudgetLine::query()->where('project_code', $project->code)->orderBy('key')->get() as $line) {
            $category = $this->categoryOf($line);
            $group = $groups[$category] ?? ['budget' => Money::zero(), 'period' => Money::zero(), 'to_date' => Money::zero()];
            $groups[$category] = [
                'budget' => bcadd($group['budget'], $this->budgetInDonor($line, $project) ?? Money::zero(), 2),
                'period' => bcadd($group['period'], Money::normalize($during->get($line->key)?->{$column}), 2),
                'to_date' => bcadd($group['to_date'], Money::normalize($toDate->get($line->key)?->{$column}), 2),
            ];
        }

        $rows = [];
        $total = ['budget' => Money::zero(), 'period' => Money::zero(), 'to_date' => Money::zero()];
        foreach ($groups as $category => $group) {
            $rows[] = ['category' => (string) $category] + $group + ['remaining' => bcsub($group['budget'], $group['to_date'], 2), 'percent' => $this->percent($group['to_date'], $group['budget'])];
            $total = ['budget' => bcadd($total['budget'], $group['budget'], 2), 'period' => bcadd($total['period'], $group['period'], 2), 'to_date' => bcadd($total['to_date'], $group['to_date'], 2)];
        }

        return ['currency' => $project->donor_currency, 'rows' => $rows, 'total' => $total + ['remaining' => bcsub($total['budget'], $total['to_date'], 2), 'percent' => $this->percent($total['to_date'], $total['budget'])]];
    }

    /**
     * Budget against actual by line, with the reason written for the period.
     *
     * @return array{lines: list<BudgetLine>, totals: array<string, array{budget: numeric-string, before: numeric-string, period: numeric-string, to_date: numeric-string, remaining: numeric-string, percent: numeric-string}>, period_usd: numeric-string}
     */
    public function budgetVsActual(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sums = fn (?CarbonImmutable $start, CarbonImmutable $end) => $this->spending($project->code)
            ->when($start !== null, fn (Builder $query) => $query->whereDate('v.date', '>=', $start?->toDateString()))
            ->whereDate('v.date', '<=', $end->toDateString())
            ->groupBy('l.budget_line_key', 'l.currency', 'l.donor_currency')
            ->selectRaw('l.budget_line_key as line_key, l.currency, l.donor_currency, sum(l.debit_usd) - sum(l.credit_usd) as usd, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_donor) - sum(l.credit_donor) as donor')
            ->get()->groupBy('line_key');

        $before = $sums(null, $from->subDay());
        $during = $sums($from, $to);
        $notes = $this->notes($project, $from, $to);

        $lines = [];
        $totals = [];
        $periodUsd = Money::zero();

        foreach (GlBudgetLine::query()->where('project_code', $project->code)->orderBy('key')->get() as $line) {
            $budget = Money::normalize($line->budget);
            $spentBefore = $this->inLineCurrency($line, $before->get($line->key, collect()));
            $spentDuring = $this->inLineCurrency($line, $during->get($line->key, collect()));
            $toDate = bcadd($spentBefore, $spentDuring, 2);
            $usd = $during->get($line->key, collect())->reduce(fn (string $carry, object $sum): string => bcadd($carry, Money::normalize($sum->usd), 2), Money::zero());
            $periodUsd = bcadd($periodUsd, $usd, 2);
            $remaining = bcsub($budget, $toDate, 2);

            $lines[] = [
                'key' => (string) $line->key, 'name' => (string) $line->name, 'category' => $this->categoryOf($line), 'account_code' => (string) $line->account_code,
                'currency' => (string) $line->currency, 'budget' => $budget, 'before' => $spentBefore, 'period' => $spentDuring, 'to_date' => $toDate,
                'remaining' => $remaining, 'percent' => $this->percent($toDate, $budget), 'period_usd' => $usd,
                'status' => bccomp($remaining, '0', 2) < 0 ? 'over' : (bccomp($remaining, '0', 2) > 0 ? 'under' : 'on'),
                'note' => $notes[$line->key] ?? '',
            ];

            $total = $totals[$line->currency] ?? ['budget' => Money::zero(), 'before' => Money::zero(), 'period' => Money::zero(), 'to_date' => Money::zero()];
            $totals[$line->currency] = [
                'budget' => bcadd($total['budget'], $budget, 2), 'before' => bcadd($total['before'], $spentBefore, 2),
                'period' => bcadd($total['period'], $spentDuring, 2), 'to_date' => bcadd($total['to_date'], $toDate, 2),
            ];
        }

        foreach ($totals as $currency => $total) {
            $totals[$currency] += ['remaining' => bcsub($total['budget'], $total['to_date'], 2), 'percent' => $this->percent($total['to_date'], $total['budget'])];
        }

        return ['lines' => $lines, 'totals' => $totals, 'period_usd' => $periodUsd];
    }

    /**
     * Every expense of the period on the project's budget lines, oldest first, in pounds, dollars and the donor's
     * currency, with the rate of each and the columns' totals.
     *
     * @return array{donor_currency: string, rows: list<Expense>, totals: array{syp: numeric-string, usd: numeric-string, donor: numeric-string}}
     */
    public function expenses(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $documents = Schema::hasColumn('gl_vouchers', 'document_type');
        $categories = Schema::hasColumn('gl_budget_lines', 'category');
        $columns = ['v.id as voucher', 'v.date', 'v.description', 'l.currency', 'l.rate', 'l.debit_orig', 'l.credit_orig', 'l.debit_usd', 'l.credit_usd', 'l.debit_donor', 'l.credit_donor', 'b.name as line'];
        $columns = [...$columns, ...($documents ? ['v.document_type', 'v.document_number', 'v.description_en'] : []), ...($categories ? ['b.category'] : [])];

        $rows = $this->spending($project->code)
            ->leftJoin('gl_budget_lines as b', 'b.key', '=', 'l.budget_line_key')
            ->whereDate('v.date', '>=', $from->toDateString())
            ->whereDate('v.date', '<=', $to->toDateString())
            ->orderBy('v.date')->orderBy('v.id')->orderBy('l.line_no')
            ->get($columns);

        $attachments = DB::table('gl_voucher_attachments')->whereIn('voucher_id', $rows->pluck('voucher')->unique()->all())->whereNull('deleted_at')
            ->groupBy('voucher_id')->selectRaw('voucher_id, count(*) as files')->pluck('files', 'voucher_id');

        $donorCurrency = $project->donor_currency;
        $showDonor = ! in_array($donorCurrency, [Currencies::BASE, 'SYP'], true);
        $expenses = [];
        $totals = ['syp' => Money::zero(), 'usd' => Money::zero(), 'donor' => Money::zero()];

        foreach ($rows as $row) {
            $usd = bcsub(Money::normalize($row->debit_usd), Money::normalize($row->credit_usd), 2);
            $day = CarbonImmutable::parse(substr((string) $row->date, 0, 10));

            if ($row->currency === 'SYP') {
                $syp = bcsub(Money::normalize($row->debit_orig), Money::normalize($row->credit_orig), 2);
                $sypRate = Money::normalize($row->rate, 6);
            } else {
                $monthly = $this->monthly->forDay('SYP', $day);
                $sypRate = $monthly['rate'] ?? null;
                $syp = $sypRate !== null ? bcmul($usd, $sypRate, 2) : null;
            }

            $donor = $showDonor ? bcsub(Money::normalize($row->debit_donor), Money::normalize($row->credit_donor), 2) : null;
            $donorRate = $donor !== null && bccomp($donor, '0', 2) !== 0 ? Money::normalize(bcdiv($usd, $donor, 6), 6) : null;

            $expenses[] = [
                'date' => $day->toDateString(), 'voucher' => (string) $row->voucher,
                'document_type' => (string) ($row->document_type ?? ''), 'document_number' => (string) ($row->document_number ?? ''),
                'description' => trim((string) ($row->description_en ?? '')) !== '' ? (string) $row->description_en : (string) $row->description,
                'line' => (string) $row->line, 'category' => trim((string) ($row->category ?? '')) !== '' ? (string) $row->category : self::NO_CATEGORY,
                'currency' => (string) $row->currency, 'syp' => $syp, 'usd' => $usd, 'donor' => $donor,
                'syp_rate' => $sypRate, 'donor_rate' => $donorRate, 'attachments' => (int) ($attachments[$row->voucher] ?? 0),
            ];

            $totals = [
                'syp' => bcadd($totals['syp'], $syp ?? Money::zero(), 2),
                'usd' => bcadd($totals['usd'], $usd, 2),
                'donor' => bcadd($totals['donor'], $donor ?? Money::zero(), 2),
            ];
        }

        return ['donor_currency' => $showDonor ? $donorCurrency : '', 'rows' => $expenses, 'totals' => $totals];
    }

    /**
     * The donor's money over the period: at its start, received, spent, and at its end.
     *
     * @return array{currency: string, opening: array{donor: numeric-string, usd: numeric-string}, received: array{donor: numeric-string, usd: numeric-string}, spent: array{donor: numeric-string, usd: numeric-string}, closing: array{donor: numeric-string, usd: numeric-string}, receipts: list<Receipt>, rate: numeric-string|null}
     */
    public function funds(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $receivedBefore = $this->received($project, null, $from->subDay());
        $spentBefore = $this->spent($project, null, $from->subDay());
        $receipts = $this->receipts($project, $from, $to);
        $received = ['donor' => Money::zero(), 'usd' => Money::zero()];

        foreach ($receipts as $receipt) {
            $received = ['donor' => bcadd($received['donor'], $receipt['donor'], 2), 'usd' => bcadd($received['usd'], $receipt['usd'], 2)];
        }

        $spent = $this->spent($project, $from, $to);
        $opening = ['donor' => bcsub($receivedBefore['donor'], $spentBefore['donor'], 2), 'usd' => bcsub($receivedBefore['usd'], $spentBefore['usd'], 2)];

        return [
            'currency' => $project->donor_currency,
            'opening' => $opening,
            'received' => $received,
            'spent' => $spent,
            'closing' => [
                'donor' => bcsub(bcadd($opening['donor'], $received['donor'], 2), $spent['donor'], 2),
                'usd' => bcsub(bcadd($opening['usd'], $received['usd'], 2), $spent['usd'], 2),
            ],
            'receipts' => $receipts,
            'rate' => bccomp($received['donor'], '0', 2) > 0 ? Money::normalize(bcdiv($received['usd'], $received['donor'], 6), 6) : null,
        ];
    }

    /** Writes, changes or (when empty) removes the reason of a line for a period. */
    public function saveNote(GlBudgetLine $line, CarbonImmutable $from, CarbonImmutable $to, string $note, int $by): void
    {
        $note = trim(mb_substr($note, 0, 2000));
        $where = ['budget_line_key' => $line->key, 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString()];

        if ($note === '') {
            BudgetLineNote::query()->where('budget_line_key', $line->key)->whereDate('period_from', $from->toDateString())->whereDate('period_to', $to->toDateString())->delete();

            return;
        }

        $existing = BudgetLineNote::query()->where('budget_line_key', $line->key)->whereDate('period_from', $from->toDateString())->whereDate('period_to', $to->toDateString())->first();

        if ($existing !== null) {
            $existing->update(['note' => $note, 'written_by' => $by]);
        } else {
            BudgetLineNote::query()->create($where + ['note' => $note, 'written_by' => $by]);
        }
    }

    public function notesAvailable(): bool
    {
        return Schema::hasTable('gl_budget_line_notes');
    }

    /** @return array<string, string> budget line key => the reason written for exactly this period */
    private function notes(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $this->notesAvailable()) {
            return [];
        }

        return BudgetLineNote::query()
            ->whereIn('budget_line_key', GlBudgetLine::query()->where('project_code', $project->code)->select('key'))
            ->whereDate('period_from', $from->toDateString())->whereDate('period_to', $to->toDateString())
            ->pluck('note', 'budget_line_key')->map(fn ($note): string => (string) $note)->all();
    }

    private function categoryOf(GlBudgetLine $line): string
    {
        $category = trim((string) $line->getAttribute('category'));

        return $category !== '' ? $category : self::NO_CATEGORY;
    }

    /** @return numeric-string|null the line's budget in the donor's currency, or null when it cannot be told */
    private function budgetInDonor(GlBudgetLine $line, GlProject $project): ?string
    {
        $budget = Money::normalize($line->budget);

        if ($line->currency === $project->donor_currency) {
            return $budget;
        }

        $rate = (string) $project->getAttribute('donor_rate');

        if ($line->currency === Currencies::BASE && is_numeric($rate) && bccomp($rate, '0', 6) > 0) {
            return bcdiv($budget, Money::normalize($rate, 6), 2);
        }

        return null;
    }

    /** The approved lines of the project's budget lines. */
    private function spending(string $projectCode): Builder
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')->whereNull('v.deleted_at')
            ->where('l.project_code', $projectCode)->whereNotNull('l.budget_line_key');
    }

    /** @param  iterable<object>  $sums */
    private function inLineCurrency(GlBudgetLine $line, iterable $sums): string
    {
        $amount = Money::zero();

        foreach ($sums as $sum) {
            if ($line->currency === Currencies::BASE) {
                $amount = bcadd($amount, Money::normalize($sum->usd), 2);
            } elseif ($sum->currency === $line->currency) {
                $amount = bcadd($amount, Money::normalize($sum->orig), 2);
            } elseif ($sum->donor_currency === $line->currency) {
                $amount = bcadd($amount, Money::normalize($sum->donor), 2);
            }
        }

        return $amount;
    }

    /** @return array{donor: numeric-string, usd: numeric-string} */
    private function spent(GlProject $project, ?CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->spending($project->code)
            ->when($from !== null, fn (Builder $query) => $query->whereDate('v.date', '>=', $from?->toDateString()))
            ->whereDate('v.date', '<=', $to->toDateString())
            ->selectRaw('sum(l.debit_usd) - sum(l.credit_usd) as usd, sum(l.debit_donor) - sum(l.credit_donor) as donor')
            ->first();
        $usd = Money::normalize($row?->usd ?? '0');

        return ['donor' => $project->donor_currency === Currencies::BASE ? $usd : Money::normalize($row?->donor ?? '0'), 'usd' => $usd];
    }

    /** @return array{donor: numeric-string, usd: numeric-string} */
    private function received(GlProject $project, ?CarbonImmutable $from, CarbonImmutable $to): array
    {
        $total = ['donor' => Money::zero(), 'usd' => Money::zero()];

        foreach ($this->receipts($project, $from, $to) as $receipt) {
            $total = ['donor' => bcadd($total['donor'], $receipt['donor'], 2), 'usd' => bcadd($total['usd'], $receipt['usd'], 2)];
        }

        return $total;
    }

    /**
     * The donor's receipts in the period: the project's income lines, each voucher once (as DonorReport counts them).
     *
     * @return list<Receipt>
     */
    private function receipts(GlProject $project, ?CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')->whereNull('v.deleted_at')
            ->where('l.project_code', $project->code)
            ->where('a.class', AccountClass::Income->value)
            ->when($from !== null, fn (Builder $query) => $query->whereDate('v.date', '>=', $from?->toDateString()))
            ->whereDate('v.date', '<=', $to->toDateString())
            ->groupBy('v.id', 'v.date', 'v.description')
            ->orderBy('v.date')->orderBy('v.id')
            ->selectRaw('v.id as voucher, v.date, v.description, sum(l.credit_usd) - sum(l.debit_usd) as usd, sum(l.credit_donor) - sum(l.debit_donor) as donor')
            ->get();

        return array_values($rows->map(function (object $row) use ($project): array {
            $usd = Money::normalize($row->usd);

            return [
                'date' => substr((string) $row->date, 0, 10), 'voucher' => (string) $row->voucher, 'description' => (string) $row->description,
                'donor' => $project->donor_currency === Currencies::BASE ? $usd : Money::normalize($row->donor), 'usd' => $usd,
            ];
        })->all());
    }

    /** @return numeric-string the share, with one decimal */
    private function percent(string $part, string $whole): string
    {
        return bccomp($whole, '0', 2) > 0 ? bcdiv(bcmul($part, '100', 4), $whole, 1) : '0.0';
    }
}
