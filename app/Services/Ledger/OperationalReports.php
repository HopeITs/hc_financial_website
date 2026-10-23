<?php

namespace App\Services\Ledger;

use App\Models\GlBudgetLine;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The operational reports of the Windows program, rebuilt on approved `gl_voucher_lines`: expenses (rexp), cash
 * balances (rcash), all budget lines (rbl), revenue and exchange (rrev), fixed assets (rfixed) and attachments
 * (rattach). They share the Windows filters: project, donor, account, line currency and a date range. The legacy
 * sub-project has no ledger counterpart, so it is not a filter here.
 *
 * Every report returns `columns` (key and type: text, ltr, date, voucher, kind, money), `rows` and `totals` (money
 * columns). Amounts are USD, or SYP at the typed rate where a USD line is converted and a SYP line stays as entered.
 *
 * @phpstan-type Report array{columns: list<array{key: string, type: string}>, rows: list<array<string, string|null>>, totals: array<string, string>}
 */
class OperationalReports
{
    public const REPORTS = ['rexp', 'rcash', 'rbl', 'rrev', 'rfixed', 'rattach'];

    /** @return Report */
    public function run(string $report, ?ReportFilters $filters = null, string $currency = 'USD', ?string $rate = null): array
    {
        $filters ??= new ReportFilters;
        $typed = $rate !== null && is_numeric($rate) && preg_match('/^\d+(\.\d+)?$/', $rate) === 1 ? $rate : '1';
        $unit = new ReportUnit($currency === 'SYP', $typed);

        return match ($report) {
            'rexp' => $this->expenses($filters, $unit),
            'rcash' => $this->cash($filters),
            'rbl' => $this->budgetLines($filters),
            'rrev' => $this->revenue($filters, $unit),
            'rfixed' => $this->fixedAssets($filters, $unit),
            'rattach' => $this->attachments($filters),
            default => abort(404),
        };
    }

    private function lines(ReportFilters $filters, bool $from = true): Builder
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->when($filters->project, fn ($query, $value) => $query->where('l.project_code', $value))
            ->when($filters->donor, fn ($query, $value) => $query->where('l.donor', $value))
            ->when($filters->account, fn ($query, $value) => $query->where('l.account_code', $value))
            ->when($filters->currency, fn ($query, $value) => $query->where('l.currency', $value))
            ->when($from ? $filters->from : null, fn ($query, $value) => $query->where('v.date', '>=', $value))
            ->when($filters->to, fn ($query, $value) => $query->where('v.date', '<=', $value));
    }

    /**
     * One side of a line in the report currency: a SYP line as entered, a dollar line times the rate in the SYP view.
     *
     * @return numeric-string
     */
    private function amount(stdClass $line, string $side, ReportUnit $unit): string
    {
        $usd = $this->money($line->{$side.'_usd'});
        if (! $unit->syp) {
            return $usd;
        }

        return $line->currency === 'SYP' ? $this->money($line->{$side.'_orig'}) : bcmul($usd, $unit->rate, 2);
    }

    private function name(stdClass $row): string
    {
        return (string) (app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en);
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /** @return Report */
    private function expenses(ReportFilters $filters, ReportUnit $unit): array
    {
        $lines = $this->lines($filters)->where('a.class', 'expenses')
            ->orderBy('v.date')->orderBy('v.id')->orderBy('l.line_no')
            ->get(['v.date', 'v.id as voucher', 'l.account_code', 'a.name_en', 'a.name_ar', 'l.project_code', 'l.budget_line_key', 'l.donor', 'v.description', 'l.currency', 'l.debit_usd', 'l.credit_usd', 'l.debit_orig', 'l.credit_orig']);

        $total = '0.00';
        $rows = [];
        foreach ($lines as $line) {
            $amount = bcsub($this->amount($line, 'debit', $unit), $this->amount($line, 'credit', $unit), 2);
            $total = bcadd($total, $amount, 2);
            $rows[] = [
                'date' => substr((string) $line->date, 0, 10), 'voucher' => $line->voucher, 'account' => $line->account_code.' · '.$this->name($line),
                'project' => $line->project_code, 'budget_line' => $line->budget_line_key, 'donor' => $line->donor, 'description' => $line->description, 'amount' => $amount,
            ];
        }

        return [
            'columns' => [['key' => 'date', 'type' => 'date'], ['key' => 'voucher', 'type' => 'voucher'], ['key' => 'account', 'type' => 'text'], ['key' => 'project', 'type' => 'ltr'], ['key' => 'budget_line', 'type' => 'text'], ['key' => 'donor', 'type' => 'text'], ['key' => 'description', 'type' => 'text'], ['key' => 'amount', 'type' => 'money']],
            'rows' => $rows,
            'totals' => ['amount' => $total],
        ];
    }

    /**
     * Cash balances per cash account and currency up to the "to" date: in the currency of the safe, with the USD value beside it.
     *
     * @return Report
     */
    private function cash(ReportFilters $filters): array
    {
        $sums = $this->lines($filters, from: false)->where('a.is_cash', true)
            ->groupBy('l.account_code', 'a.name_en', 'a.name_ar', 'l.currency')
            ->orderBy('l.account_code')->orderBy('l.currency')
            ->selectRaw('l.account_code, a.name_en, a.name_ar, l.currency, sum(l.debit_orig) - sum(l.credit_orig) as amount, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        $usd = '0.00';
        $rows = [];
        foreach ($sums as $sum) {
            $usd = bcadd($usd, $this->money($sum->usd), 2);
            $rows[] = ['account' => $sum->account_code.' · '.$this->name($sum), 'currency' => $sum->currency, 'amount' => $this->money($sum->amount), 'usd' => $this->money($sum->usd)];
        }

        return [
            'columns' => [['key' => 'account', 'type' => 'text'], ['key' => 'currency', 'type' => 'ltr'], ['key' => 'amount', 'type' => 'money'], ['key' => 'usd', 'type' => 'money']],
            'rows' => $rows,
            'totals' => ['usd' => $usd],
        ];
    }

    /**
     * Every budget line with its actual, in the line's own currency, counted as ProjectBalances::budgetVsActual counts it.
     *
     * @return Report
     */
    private function budgetLines(ReportFilters $filters): array
    {
        $actuals = $this->lines($filters)->whereNotNull('l.budget_line_key')
            ->groupBy('l.budget_line_key', 'l.currency', 'l.donor_currency')
            ->selectRaw('l.budget_line_key as line_key, l.currency, l.donor_currency, sum(l.debit_usd) - sum(l.credit_usd) as usd, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_donor) - sum(l.credit_donor) as donor')
            ->get()->groupBy('line_key');

        $lines = GlBudgetLine::query()
            ->when($filters->project, fn ($query, $value) => $query->where('project_code', $value))
            ->when($filters->donor, fn ($query, $value) => $query->where('donor', $value))
            ->when($filters->account, fn ($query, $value) => $query->where('account_code', $value))
            ->when($filters->currency, fn ($query, $value) => $query->where('currency', $value))
            ->orderBy('project_code')->orderBy('key')->get();

        $rows = [];
        foreach ($lines as $line) {
            $actual = '0.00';
            foreach ($actuals->get($line->key, []) as $sum) {
                if ($line->currency === 'USD') {
                    $actual = bcadd($actual, $this->money($sum->usd), 2);
                } elseif ($line->currency === 'SYP' && $sum->currency === 'SYP') {
                    $actual = bcadd($actual, $this->money($sum->orig), 2);
                } elseif ($line->currency !== 'SYP' && $sum->donor_currency === $line->currency) {
                    $actual = bcadd($actual, $this->money($sum->donor), 2);
                }
            }

            $budget = $this->money($line->budget);
            $rows[] = [
                'project' => $line->project_code, 'name' => $line->name, 'account' => $line->account_code, 'currency' => $line->currency,
                'budget' => $budget, 'actual' => $actual, 'remaining' => bcsub($budget, $actual, 2),
            ];
        }

        return [
            'columns' => [['key' => 'project', 'type' => 'ltr'], ['key' => 'name', 'type' => 'text'], ['key' => 'account', 'type' => 'ltr'], ['key' => 'currency', 'type' => 'ltr'], ['key' => 'budget', 'type' => 'money'], ['key' => 'actual', 'type' => 'money'], ['key' => 'remaining', 'type' => 'money']],
            'rows' => $rows,
            'totals' => [],
        ];
    }

    /**
     * Income lines (credit minus debit) and exchange: transfer vouchers' movements on cash accounts, with the amount in the safe's own currency beside it.
     *
     * @return Report
     */
    private function revenue(ReportFilters $filters, ReportUnit $unit): array
    {
        $lines = $this->lines($filters)
            ->where(fn ($query) => $query->where('a.class', 'income')->orWhere(fn ($q) => $q->where('v.type', 'TRF')->where('a.is_cash', true)))
            ->orderBy('v.date')->orderBy('v.id')->orderBy('l.line_no')
            ->get(['v.date', 'v.id as voucher', 'a.class', 'l.account_code', 'a.name_en', 'a.name_ar', 'l.project_code', 'l.donor', 'l.currency', 'l.debit_usd', 'l.credit_usd', 'l.debit_orig', 'l.credit_orig']);

        $total = '0.00';
        $rows = [];
        foreach ($lines as $line) {
            $revenue = $line->class === 'income';
            $debit = $this->amount($line, 'debit', $unit);
            $credit = $this->amount($line, 'credit', $unit);
            $amount = $revenue ? bcsub($credit, $debit, 2) : bcsub($debit, $credit, 2);
            $original = $revenue ? bcsub($this->money($line->credit_orig), $this->money($line->debit_orig), 2) : bcsub($this->money($line->debit_orig), $this->money($line->credit_orig), 2);
            if ($revenue) {
                $total = bcadd($total, $amount, 2);
            }
            $rows[] = [
                'kind' => $revenue ? 'revenue' : 'exchange', 'date' => substr((string) $line->date, 0, 10), 'voucher' => $line->voucher,
                'account' => $line->account_code.' · '.$this->name($line), 'project' => $line->project_code, 'donor' => $line->donor,
                'currency' => $line->currency, 'original' => $original, 'amount' => $amount,
            ];
        }

        return [
            'columns' => [['key' => 'kind', 'type' => 'kind'], ['key' => 'date', 'type' => 'date'], ['key' => 'voucher', 'type' => 'voucher'], ['key' => 'account', 'type' => 'text'], ['key' => 'project', 'type' => 'ltr'], ['key' => 'donor', 'type' => 'text'], ['key' => 'currency', 'type' => 'ltr'], ['key' => 'original', 'type' => 'money'], ['key' => 'amount', 'type' => 'money']],
            'rows' => $rows,
            'totals' => ['amount' => $total],
        ];
    }

    /**
     * Fixed assets: the balance of every "Property and equipment, net" account per project (cost less accumulated depreciation).
     *
     * @return Report
     */
    private function fixedAssets(ReportFilters $filters, ReportUnit $unit): array
    {
        $lines = $this->lines($filters)->where('a.statement_line', 'Property and equipment, net')
            ->orderBy('l.account_code')->orderBy('l.project_code')
            ->get(['l.account_code', 'a.name_en', 'a.name_ar', 'l.project_code', 'l.currency', 'l.debit_usd', 'l.credit_usd', 'l.debit_orig', 'l.credit_orig']);

        $balances = [];
        foreach ($lines as $line) {
            $key = $line->account_code.'|'.$line->project_code;
            $balances[$key] ??= ['account' => $line->account_code.' · '.$this->name($line), 'project' => $line->project_code, 'amount' => '0.00'];
            $balances[$key]['amount'] = bcadd($balances[$key]['amount'], bcsub($this->amount($line, 'debit', $unit), $this->amount($line, 'credit', $unit), 2), 2);
        }

        $total = '0.00';
        foreach ($balances as $balance) {
            $total = bcadd($total, $balance['amount'], 2);
        }

        return [
            'columns' => [['key' => 'account', 'type' => 'text'], ['key' => 'project', 'type' => 'ltr'], ['key' => 'amount', 'type' => 'money']],
            'rows' => array_values($balances),
            'totals' => ['amount' => $total],
        ];
    }

    /**
     * One row per attachment of an approved voucher, with the voucher's date and description.
     *
     * @return Report
     */
    private function attachments(ReportFilters $filters): array
    {
        $onLines = $filters->project !== null || $filters->donor !== null || $filters->account !== null || $filters->currency !== null;

        $files = DB::table('gl_voucher_attachments as t')
            ->join('gl_vouchers as v', 'v.id', '=', 't.voucher_id')
            ->whereNull('t.deleted_at')->whereNull('v.deleted_at')->where('v.status', 'approved')
            ->when($filters->from, fn ($query, $value) => $query->where('v.date', '>=', $value))
            ->when($filters->to, fn ($query, $value) => $query->where('v.date', '<=', $value))
            ->when($onLines, fn ($query) => $query->whereExists(
                fn ($sub) => $sub->selectRaw('1')->from('gl_voucher_lines as l')->whereColumn('l.voucher_id', 'v.id')
                    ->when($filters->project, fn ($q, $value) => $q->where('l.project_code', $value))
                    ->when($filters->donor, fn ($q, $value) => $q->where('l.donor', $value))
                    ->when($filters->account, fn ($q, $value) => $q->where('l.account_code', $value))
                    ->when($filters->currency, fn ($q, $value) => $q->where('l.currency', $value))
            ))
            ->orderBy('v.date')->orderBy('v.id')->orderBy('t.id')
            ->get(['v.date', 'v.id as voucher', 'v.description', 't.original_name', 't.size']);

        $rows = [];
        foreach ($files as $file) {
            $rows[] = ['date' => substr((string) $file->date, 0, 10), 'voucher' => $file->voucher, 'description' => $file->description, 'file' => $file->original_name, 'size' => (string) $file->size];
        }

        return [
            'columns' => [['key' => 'date', 'type' => 'date'], ['key' => 'voucher', 'type' => 'voucher'], ['key' => 'description', 'type' => 'text'], ['key' => 'file', 'type' => 'text'], ['key' => 'size', 'type' => 'ltr']],
            'rows' => $rows,
            'totals' => [],
        ];
    }
}
