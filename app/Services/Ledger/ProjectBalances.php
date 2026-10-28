<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Project balances read from approved voucher lines: income, expenses, cash held, restricted fund balance,
 * budget against actual per budget line, the donor-currency view and where each project's cash sits.
 *
 * Expenses leave out 5703 depreciation (decision D10). Like the trial balance, the SYP view multiplies a USD
 * amount by the typed rate and keeps a SYP line as entered; two currencies are never added without that
 * explicit conversion.
 */
class ProjectBalances
{
    public function __construct(private readonly Currencies $currencies) {}

    public const DEPRECIATION = '5703';

    private const RESTRICTED = 'With donor restrictions';

    private const RESTRICTED_FUND_ACCOUNT = '3201';

    /**
     * @return array{rows: list<array{code: string, name: string, restriction: string, donor_currency: string, income: string, expenses: string, result: string, cash: string, restricted: string, donor_income: string, donor_expenses: string}>, totals: array{income: string, expenses: string, result: string, cash: string, restricted: string}}
     */
    public function report(string $currency = 'USD', ?string $rate = null): array
    {
        $currency = $this->currencies->isActive($currency) ? $currency : Currencies::BASE;
        $rate = $currency !== Currencies::BASE ? $this->rate($rate) : '1';
        $projects = GlProject::query()->orderBy('code')->get()->keyBy('code');

        $acc = [];
        foreach ($this->sums() as $line) {
            if (! $projects->has($line->project_code)) {
                continue;
            }

            $net = $this->net($line, $currency, $rate);
            $acc[$line->project_code] ??= $this->blank();
            $row = &$acc[$line->project_code];

            if ($line->class === 'income') {
                $row['income'] = bcsub($row['income'], $net, 2);
                $row['donor_income'] = bcadd($row['donor_income'], bcsub($this->money($line->credit_donor), $this->money($line->debit_donor), 2), 2);
            } elseif ($line->class === 'expenses' && $line->account_code !== self::DEPRECIATION) {
                $row['expenses'] = bcadd($row['expenses'], $net, 2);
                $row['donor_expenses'] = bcadd($row['donor_expenses'], bcsub($this->money($line->debit_donor), $this->money($line->credit_donor), 2), 2);
            }
            if ($line->is_cash) {
                $row['cash'] = bcadd($row['cash'], $net, 2);
            }
            if ($line->account_code === self::RESTRICTED_FUND_ACCOUNT) {
                $row['fund'] = bcsub($row['fund'], $net, 2);
            }
            unset($row);
        }

        $rows = [];
        $totals = array_fill_keys(['income', 'expenses', 'result', 'cash', 'restricted'], '0.00');

        foreach ($acc as $code => $sum) {
            $project = $projects[$code];
            $result = bcsub($sum['income'], $sum['expenses'], 2);
            // Restricted money: the carried-forward fund plus the result so far, for projects with donor restrictions only.
            $restricted = $project->restriction === self::RESTRICTED ? bcadd($sum['fund'], $result, 2) : '0.00';

            $row = [
                'code' => $code,
                'name' => $project->name,
                'restriction' => $project->restriction,
                'donor_currency' => $project->donor_currency,
                'income' => $sum['income'],
                'expenses' => $sum['expenses'],
                'result' => $result,
                'cash' => $sum['cash'],
                'restricted' => $restricted,
                'donor_income' => $sum['donor_income'],
                'donor_expenses' => $sum['donor_expenses'],
            ];
            $rows[] = $row;

            foreach (array_keys($totals) as $key) {
                $totals[$key] = bcadd($totals[$key], $row[$key], 2);
            }
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** @return array{income: numeric-string, expenses: numeric-string, cash: numeric-string, fund: numeric-string, donor_income: numeric-string, donor_expenses: numeric-string} */
    private function blank(): array
    {
        return ['income' => '0.00', 'expenses' => '0.00', 'cash' => '0.00', 'fund' => '0.00', 'donor_income' => '0.00', 'donor_expenses' => '0.00'];
    }

    /**
     * Budget against actual for each budget line of one project, in the line's own currency.
     *
     * @return list<array{key: string, name: string, account_code: string, currency: string, budget: numeric-string, actual: numeric-string, remaining: numeric-string, percent: numeric-string}>
     */
    public function budgetVsActual(string $projectCode): array
    {
        $actuals = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->where('l.project_code', $projectCode)
            ->whereNotNull('l.budget_line_key')
            ->groupBy('l.budget_line_key', 'l.currency', 'l.donor_currency')
            ->selectRaw('l.budget_line_key as line_key, l.currency, l.donor_currency, sum(l.debit_usd) - sum(l.credit_usd) as usd, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_donor) - sum(l.credit_donor) as donor')
            ->get()
            ->groupBy('line_key');

        $rows = [];
        foreach (GlBudgetLine::query()->where('project_code', $projectCode)->orderBy('key')->get() as $line) {
            $actual = '0.00';
            foreach ($actuals->get($line->key, []) as $sum) {
                // A USD budget counts every line at its USD value, a SYP budget the SYP lines as entered,
                // and a donor-currency budget (EUR) the donor amounts recorded in that currency.
                if ($line->currency === 'USD') {
                    $actual = bcadd($actual, $this->money($sum->usd), 2);
                } elseif ($sum->currency === $line->currency) {
                    $actual = bcadd($actual, $this->money($sum->orig), 2);
                } elseif ($sum->donor_currency === $line->currency) {
                    $actual = bcadd($actual, $this->money($sum->donor), 2);
                }
            }

            $budget = $this->money($line->budget);

            $rows[] = [
                'key' => $line->key,
                'name' => $line->name,
                'account_code' => $line->account_code,
                'currency' => $line->currency,
                'budget' => $budget,
                'actual' => $actual,
                'remaining' => bcsub($budget, $actual, 2),
                'percent' => bccomp($budget, '0', 2) > 0 ? bcdiv(bcmul($actual, '100', 4), $budget, 1) : '0.0',
            ];
        }

        return $rows;
    }

    /**
     * Where the funds are: each project's balance split by cash account and by currency. The amount is in the
     * cash account's own currency; the USD value sits beside it, never added to it.
     *
     * @return list<array{project_code: string, project_name: string, account_code: string, account_name_en: string, account_name_ar: string, currency: string, amount: numeric-string, usd: numeric-string}>
     */
    public function fundLocations(?string $projectCode = null): array
    {
        $sums = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->where('a.is_cash', true)
            ->whereNotNull('l.project_code')
            ->when($projectCode !== null, fn ($query) => $query->where('l.project_code', $projectCode))
            ->groupBy('l.project_code', 'l.account_code', 'l.currency')
            ->orderBy('l.project_code')->orderBy('l.account_code')->orderBy('l.currency')
            ->selectRaw('l.project_code, l.account_code, l.currency, sum(l.debit_orig) - sum(l.credit_orig) as amount, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        $projects = GlProject::query()->pluck('name', 'code');
        $accounts = GlAccount::query()->whereIn('code', $sums->pluck('account_code')->unique())->get()->keyBy('code');

        $rows = [];
        foreach ($sums as $sum) {
            $amount = $this->money($sum->amount);
            $usd = $this->money($sum->usd);

            if (bccomp($amount, '0', 2) === 0 && bccomp($usd, '0', 2) === 0) {
                continue;
            }

            $rows[] = [
                'project_code' => (string) $sum->project_code,
                'project_name' => (string) $projects->get($sum->project_code, $sum->project_code),
                'account_code' => (string) $sum->account_code,
                'account_name_en' => $accounts[$sum->account_code]->name_en,
                'account_name_ar' => $accounts[$sum->account_code]->name_ar,
                'currency' => (string) $sum->currency,
                'amount' => $amount,
                'usd' => $usd,
            ];
        }

        return $rows;
    }

    /**
     * One row per project, account and line currency, with the account's class and cash flag.
     *
     * @return Collection<int, \stdClass>
     */
    private function sums(): Collection
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->whereNotNull('l.project_code')
            ->groupBy('l.project_code', 'l.account_code', 'l.currency', 'a.class', 'a.is_cash')
            ->selectRaw(
                'l.project_code, l.account_code, l.currency, a.class, a.is_cash, '
                .'sum(l.debit_usd) as debit_usd, sum(l.credit_usd) as credit_usd, '
                .'sum(l.debit_orig) as debit_orig, sum(l.credit_orig) as credit_orig, '
                .'sum(l.debit_donor) as debit_donor, sum(l.credit_donor) as credit_donor'
            )
            ->get();
    }

    /**
     * Debit minus credit of one group in the chosen view.
     *
     * @param  numeric-string  $rate
     * @return numeric-string
     */
    private function net(\stdClass $line, string $currency, string $rate): string
    {
        if ($currency !== Currencies::BASE && $line->currency === $currency) {
            return bcsub($this->money($line->debit_orig), $this->money($line->credit_orig), 2);
        }

        $usd = bcsub($this->money($line->debit_usd), $this->money($line->credit_usd), 2);

        return $currency !== Currencies::BASE ? $this->currencies->fromUsd($currency, $usd, $rate) : $usd;
    }

    /** @return numeric-string */
    private function rate(?string $rate): string
    {
        return is_numeric($rate) && bccomp((string) $rate, '0', 6) > 0 ? number_format((float) $rate, 6, '.', '') : '1.000000';
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
