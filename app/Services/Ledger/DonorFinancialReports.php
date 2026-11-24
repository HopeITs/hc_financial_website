<?php

namespace App\Services\Ledger;

use App\Enums\AccountClass;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The donor's financial reports of one project for a period (finance request of 2026-11-26), from approved vouchers:
 *
 * - budget against actual: each budget line's budget, what was spent before the period, in it and to its end, what
 *   remains and the share spent, in the line's own currency (as ProjectBalances::budgetVsActual counts it: a dollar
 *   line at the dollar value, any other at the amount entered in its currency or recorded in the donor's);
 * - the expenses of the period, line by line, with the voucher, the budget line and the attachments;
 * - the funds statement: what the donor's money was at the start of the period, what came in and went out in it, and
 *   what is left at its end, in the donor's currency and in dollars.
 *
 * @phpstan-type BudgetLine array{key: string, name: string, account_code: string, currency: string, budget: numeric-string, before: numeric-string, period: numeric-string, to_date: numeric-string, remaining: numeric-string, percent: numeric-string, period_usd: numeric-string}
 * @phpstan-type Expense array{date: string, voucher: string, line: string, account: string, description: string, currency: string, amount: numeric-string, usd: numeric-string, attachments: int}
 * @phpstan-type Receipt array{date: string, voucher: string, description: string, donor: numeric-string, usd: numeric-string}
 */
class DonorFinancialReports
{
    /**
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

            $lines[] = [
                'key' => (string) $line->key, 'name' => (string) $line->name, 'account_code' => (string) $line->account_code, 'currency' => (string) $line->currency,
                'budget' => $budget, 'before' => $spentBefore, 'period' => $spentDuring, 'to_date' => $toDate,
                'remaining' => bcsub($budget, $toDate, 2), 'percent' => $this->percent($toDate, $budget), 'period_usd' => $usd,
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
     * Every expense of the period on the project's budget lines, oldest first.
     *
     * @return array{rows: list<Expense>, totals: array<string, numeric-string>, usd: numeric-string}
     */
    public function expenses(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->spending($project->code)
            ->leftJoin('gl_budget_lines as b', 'b.key', '=', 'l.budget_line_key')
            ->whereDate('v.date', '>=', $from->toDateString())
            ->whereDate('v.date', '<=', $to->toDateString())
            ->orderBy('v.date')->orderBy('v.id')->orderBy('l.line_no')
            ->get(['v.id as voucher', 'v.date', 'v.description', 'l.account_code', 'l.currency', 'l.debit_orig', 'l.credit_orig', 'l.debit_usd', 'l.credit_usd', 'b.name as line']);

        $attachments = DB::table('gl_voucher_attachments')->whereIn('voucher_id', $rows->pluck('voucher')->unique()->all())->whereNull('deleted_at')
            ->groupBy('voucher_id')->selectRaw('voucher_id, count(*) as files')->pluck('files', 'voucher_id');

        $expenses = [];
        $totals = [];
        $usd = Money::zero();

        foreach ($rows as $row) {
            $amount = bcsub(Money::normalize($row->debit_orig), Money::normalize($row->credit_orig), 2);
            $inUsd = bcsub(Money::normalize($row->debit_usd), Money::normalize($row->credit_usd), 2);
            $expenses[] = [
                'date' => substr((string) $row->date, 0, 10), 'voucher' => (string) $row->voucher, 'line' => (string) $row->line,
                'account' => (string) $row->account_code, 'description' => (string) $row->description, 'currency' => (string) $row->currency,
                'amount' => $amount, 'usd' => $inUsd, 'attachments' => (int) ($attachments[$row->voucher] ?? 0),
            ];
            $totals[$row->currency] = bcadd($totals[$row->currency] ?? Money::zero(), $amount, 2);
            $usd = bcadd($usd, $inUsd, 2);
        }

        return ['rows' => $expenses, 'totals' => $totals, 'usd' => $usd];
    }

    /**
     * The donor's money over the period: at its start, received, spent, and at its end.
     *
     * @return array{currency: string, opening: array{donor: numeric-string, usd: numeric-string}, received: array{donor: numeric-string, usd: numeric-string}, spent: array{donor: numeric-string, usd: numeric-string}, closing: array{donor: numeric-string, usd: numeric-string}, receipts: list<Receipt>, rate: numeric-string|null}
     */
    public function funds(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $currency = $project->donor_currency;
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
            'currency' => $currency,
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
