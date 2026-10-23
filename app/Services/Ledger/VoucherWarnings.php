<?php

namespace App\Services\Ledger;

use App\Enums\AccountClass;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The two soft warnings of the voucher builder (Financial Manager decisions R1-4b and R3-3). They never block a voucher:
 * money spent on a project after its end date, and a budget line spent past what is left of it. The amounts already
 * spent are the approved lines of the budget line, in USD, taken to the budget's currency at the project's donor rate.
 */
class VoucherWarnings
{
    /** @return list<string> */
    public function for(VoucherDraft $draft): array
    {
        return [...$this->afterEndDate($draft), ...$this->overBudget($draft)];
    }

    /** @return list<string> */
    private function afterEndDate(VoucherDraft $draft): array
    {
        $codes = collect($draft->lines)->pluck('projectCode')->filter()->unique()->all();
        if ($codes === []) {
            return [];
        }

        $projects = GlProject::query()->whereIn('code', $codes)->whereNotNull('end_date')->get();
        $accounts = GlAccount::query()->whereIn('code', collect($draft->lines)->pluck('accountCode')->unique()->all())->get()->keyBy('code');
        $warnings = [];

        foreach ($projects as $project) {
            $endDate = $project->end_date;
            if ($endDate === null || $draft->date->toDateString() <= $endDate->toDateString()) {
                continue;
            }

            $spends = collect($draft->lines)->contains(fn (LineDraft $line) => $line->projectCode === $project->code
                && ($draft->type === VoucherType::Payment
                    || (bccomp($line->debitUsd, '0', 2) > 0 && $accounts->get($line->accountCode)?->class === AccountClass::Expenses)));

            if ($spends) {
                $warnings[] = __('ledger.warnings.after_end', ['project' => $project->code, 'end' => $endDate->toDateString()]);
            }
        }

        return $warnings;
    }

    /** @return list<string> */
    private function overBudget(VoucherDraft $draft): array
    {
        $thisVoucher = [];
        foreach ($draft->lines as $line) {
            if ($line->budgetLineKey !== null) {
                $thisVoucher[$line->budgetLineKey] = bcadd($thisVoucher[$line->budgetLineKey] ?? '0.00', bcsub($line->debitUsd, $line->creditUsd, 2), 2);
            }
        }

        $thisVoucher = array_filter($thisVoucher, fn (string $usd) => bccomp($usd, '0', 2) > 0);
        if ($thisVoucher === []) {
            return [];
        }

        $keys = array_map('strval', array_keys($thisVoucher));
        $budgetLines = GlBudgetLine::query()->whereIn('key', $keys)->with('project')->get();
        $spent = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at')
            ->whereIn('l.budget_line_key', $keys)
            ->groupBy('l.budget_line_key')
            ->selectRaw('l.budget_line_key as line_key, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->pluck('usd', 'line_key');

        $warnings = [];

        foreach ($budgetLines as $budgetLine) {
            $inBudgetCurrency = fn (string $usd): ?string => $this->convert($usd, $budgetLine);
            $before = $inBudgetCurrency(Money::normalize($spent[$budgetLine->key] ?? null));
            $adding = $inBudgetCurrency($thisVoucher[$budgetLine->key]);

            if ($before === null || $adding === null) {
                continue;
            }

            $budget = Money::normalize($budgetLine->budget);
            if (bccomp(bcadd($before, $adding, 2), $budget, 2) > 0) {
                $left = bcsub($budget, $before, 2);
                $warnings[] = __('ledger.warnings.over_budget', [
                    'line' => $budgetLine->key,
                    'left' => number_format((float) (bccomp($left, '0', 2) > 0 ? $left : '0'), 2),
                    'amount' => number_format((float) $adding, 2),
                    'currency' => $budgetLine->currency,
                ]);
            }
        }

        return $warnings;
    }

    /**
     * USD in the budget line's currency: itself for USD, the project's donor rate for its donor currency, otherwise
     * unknown (no warning).
     *
     * @return numeric-string|null
     */
    private function convert(string $usd, GlBudgetLine $budgetLine): ?string
    {
        if ($budgetLine->currency === 'USD') {
            return Money::normalize($usd);
        }

        $project = $budgetLine->project;
        $rate = $project === null ? '0.000000' : Money::normalize($project->donor_rate, 6);
        if ($project === null || $project->donor_currency !== $budgetLine->currency || bccomp($rate, '0', 6) <= 0) {
            return null;
        }

        return Money::normalize(bcdiv($usd, $rate, 6));
    }
}
