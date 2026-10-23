<?php

namespace App\Services\Payroll;

use App\Enums\FundSource;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\Voucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Salary allocation (voucher type ALC): one salary, or one invoice shared by projects, split over budget lines. The lines
 * are proposed from the employee's payroll split, the accountant adjusts them, and the voucher has one debit line per
 * budget line and one credit line per project on the paying cash account. Cents left by rounding go to the last line.
 * An allocation never moves pounds from the general account to a project: that is a transfer (TRF).
 */
class SalaryAllocation
{
    public function __construct(
        private readonly PayrollSplits $splits,
        private readonly PostVoucher $posting,
    ) {}

    /**
     * The lines of the split in force for the month, in the salary's own currency. Each share is rounded to the cent and
     * the last line takes what is left, so the lines always add up to the salary.
     *
     * @return list<array{budget_line: string, project: string, percent: string, amount: string}>
     *
     * @throws PayrollException when the amount is not valid or the employee has no split for the month
     */
    public function propose(int $employeeId, CarbonImmutable $month, string $amount): array
    {
        $total = $this->amount($amount);

        if ($total === null || bccomp($total, '0', 2) <= 0) {
            throw new PayrollException([__('salaries.problem.amount')]);
        }

        $split = $this->splits->forMonth($employeeId, $month);

        if ($split->isEmpty()) {
            throw new PayrollException([__('salaries.problem.no_split', ['employee' => $employeeId, 'month' => $month->format('Y-m')])]);
        }

        $lines = [];
        $given = '0.00';
        $last = $split->count() - 1;

        foreach ($split->values() as $index => $row) {
            $share = $index === $last
                ? bcsub($total, $given, 2)
                : Money::normalize(bcdiv(bcmul($total, Money::normalize($row->percent), 6), '100', 6));
            $given = bcadd($given, $share, 2);
            $lines[] = ['budget_line' => $row->budget_line_key, 'project' => $row->project_code, 'percent' => $row->percent, 'amount' => $share];
        }

        return $lines;
    }

    /**
     * Projects that ended before the voucher date. A warning only: the voucher can still be saved.
     *
     * @param  list<array<string, string>>  $lines
     * @return list<string>
     */
    public function warnings(array $lines, CarbonImmutable $date): array
    {
        $projects = GlBudgetLine::query()->whereIn('key', array_column($lines, 'budget_line'))->pluck('project_code')->unique();
        $warnings = [];

        foreach (GlProject::query()->whereIn('code', $projects)->get() as $project) {
            if ($project->end_date !== null && $project->end_date->startOfDay()->lt($date->startOfDay())) {
                $warnings[] = __('salaries.warning.project_ended', ['project' => $project->code, 'date' => $project->end_date->toDateString()]);
            }
        }

        return $warnings;
    }

    /**
     * Posts the allocation as an ALC voucher waiting for approval.
     *
     * @param  list<array<string, string>>  $lines  the debit lines in the salary's currency (budget_line, amount)
     * @param  string  $total  the salary or invoice amount; the lines must add up to it
     *
     * @throws PayrollException
     * @throws LedgerRuleException
     */
    public function post(
        CarbonImmutable $date,
        string $description,
        string $currency,
        string $rate,
        string $total,
        array $lines,
        string $cashAccount,
        FundSource $fundSource,
        int $preparedBy,
    ): Voucher {
        $problems = [];
        $total = $this->amount($total);
        $lines = array_values(array_filter($lines, fn (array $line) => trim($line['budget_line'] ?? '') !== '' || trim($line['amount'] ?? '') !== ''));

        if ($total === null || bccomp($total, '0', 2) <= 0) {
            $problems[] = __('salaries.problem.amount');
        }

        if (! in_array($currency, ['USD', 'SYP'], true)) {
            $problems[] = __('salaries.problem.currency');
        }

        $rate = $currency === 'SYP' ? $this->rate($rate) : '1.000000';
        if ($rate === null) {
            $problems[] = __('salaries.problem.rate');
        }

        $account = GlAccount::query()->find($cashAccount);
        if ($account === null || ! $account->is_cash || $account->currency !== $currency) {
            $problems[] = __('salaries.problem.cash_account', ['currency' => $currency]);
        }

        if ($lines === []) {
            $problems[] = __('salaries.problem.no_lines');
        }

        $budgetLines = GlBudgetLine::query()->with('project')->whereIn('key', array_column($lines, 'budget_line'))->get()->keyBy('key');
        $amounts = [];
        $sum = '0.00';

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $amount = $this->amount($line['amount'] ?? '');

            if (! $budgetLines->has($line['budget_line'] ?? '')) {
                $problems[] = __('salaries.problem.budget_line', ['line' => $number]);
            }

            if ($amount === null || bccomp($amount, '0', 2) <= 0) {
                $problems[] = __('salaries.problem.line_amount', ['line' => $number]);

                continue;
            }

            $amounts[$index] = $amount;
            $sum = bcadd($sum, $amount, 2);
        }

        if ($problems === [] && $total !== null && bccomp($sum, $total, 2) !== 0) {
            $problems[] = __('salaries.problem.lines_total', ['lines' => $sum, 'total' => $total]);
        }

        if ($problems !== [] || $total === null || $rate === null) {
            throw new PayrollException($problems === [] ? [__('salaries.problem.amount')] : $problems);
        }

        $draft = new VoucherDraft(
            type: VoucherType::Allocation,
            date: $date,
            preparedBy: $preparedBy,
            lines: $this->draftLines($lines, $amounts, $budgetLines->all(), $currency, $rate, $total, $cashAccount, $fundSource),
            description: trim($description) !== '' ? trim($description) : null,
        );

        return $this->posting->post($draft);
    }

    /**
     * @param  list<array<string, string>>  $lines
     * @param  array<int, numeric-string>  $amounts
     * @param  numeric-string  $rate
     * @param  numeric-string  $total
     * @param  array<string, GlBudgetLine>  $budgetLines
     * @return list<LineDraft>
     */
    private function draftLines(array $lines, array $amounts, array $budgetLines, string $currency, string $rate, string $total, string $cashAccount, FundSource $fundSource): array
    {
        $totalUsd = $this->toUsd($total, $currency, $rate);
        $givenUsd = '0.00';
        $debits = [];
        $cash = [];
        $last = array_key_last($lines);

        foreach ($lines as $index => $line) {
            $budgetLine = $budgetLines[$line['budget_line']];
            $project = $budgetLine->project;
            $usd = $index === $last ? bcsub($totalUsd, $givenUsd, 2) : $this->share($totalUsd, $amounts[$index], $total);
            $givenUsd = bcadd($givenUsd, $usd, 2);

            $debits[] = new LineDraft(
                accountCode: $budgetLine->account_code,
                currency: $currency,
                debit: $amounts[$index],
                rate: $rate,
                debitUsd: $usd,
                projectCode: $budgetLine->project_code,
                budgetLineKey: $budgetLine->key,
                donor: $project?->donor,
                restriction: $project?->restriction,
                donorCurrency: $project?->donor_currency,
                debitDonor: $project !== null ? $this->donorAmount($usd, $project) : null,
            );

            $cash[$budgetLine->project_code]['orig'] = bcadd($cash[$budgetLine->project_code]['orig'] ?? '0.00', $amounts[$index], 2);
            $cash[$budgetLine->project_code]['usd'] = bcadd($cash[$budgetLine->project_code]['usd'] ?? '0.00', $usd, 2);
            $cash[$budgetLine->project_code]['project'] = $project;
        }

        $credits = [];
        foreach ($cash as $code => $sum) {
            $project = $sum['project'];
            $credits[] = new LineDraft(
                accountCode: $cashAccount,
                currency: $currency,
                credit: $sum['orig'],
                rate: $rate,
                creditUsd: $sum['usd'],
                projectCode: (string) $code,
                fundSource: $fundSource,
                donor: $project?->donor,
                restriction: $project?->restriction,
                donorCurrency: $project?->donor_currency,
                creditDonor: $project !== null ? $this->donorAmount($sum['usd'], $project) : null,
            );
        }

        return [...$debits, ...$credits];
    }

    /**
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate
     * @return numeric-string
     */
    private function toUsd(string $amount, string $currency, string $rate): string
    {
        return $currency === 'USD' ? Money::normalize($amount) : Money::normalize(bcdiv($amount, $rate, 6));
    }

    /**
     * The part of the USD total that belongs to a line, by its share of the original total.
     *
     * @param  numeric-string  $totalUsd
     * @param  numeric-string  $amount
     * @param  numeric-string  $total
     * @return numeric-string
     */
    private function share(string $totalUsd, string $amount, string $total): string
    {
        return Money::normalize(bcdiv(bcmul($totalUsd, $amount, 6), $total, 6));
    }

    /**
     * @param  numeric-string  $usd
     * @return numeric-string
     */
    private function donorAmount(string $usd, GlProject $project): string
    {
        $donorRate = Money::normalize($project->donor_rate, 6);

        if ($project->donor_currency === 'USD' || bccomp($donorRate, '0', 6) <= 0) {
            return $usd;
        }

        return Money::normalize(bcdiv($usd, $donorRate, 6));
    }

    /** @return numeric-string|null */
    private function amount(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }

    /** @return numeric-string|null */
    private function rate(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        if (preg_match('/^\d+(\.\d+)?$/', $clean) !== 1) {
            return null;
        }

        $rate = Money::normalize($clean, 6);

        return bccomp($rate, '0', 6) > 0 ? $rate : null;
    }
}
