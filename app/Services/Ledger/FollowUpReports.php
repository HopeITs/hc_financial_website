<?php

namespace App\Services\Ledger;

use App\Enums\TransferKind;
use App\Enums\VoucherStatus;
use App\Services\Loans\MicroLoans;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The follow-up reports on approved voucher lines (amounts are USD unless a column says otherwise):
 * `recon` the transfer company's pool (1161) against project and city general balances, month by month;
 * `mapprovals` one month, per project: USD at the transfer company (1161) at the start, the SYP approved and its USD cost, the USD approved, other movements, USD at the end, and the SYP held at the start and end;
 * `uncomp` cash received without a project that is still uncompensated, per city, in age buckets;
 * `xreserve` the 5503 exchange differences booked by allocation and local-exchange transfers under each city's general SYP account;
 * `advances` open advances (1201) by person, no ageing: a settlement difference stays on the person as a receivable;
 * `loans` micro loan collection and arrears.
 * Each returns `columns` (key and type: text, ltr, date, money), `rows` and `totals`, the shape of OperationalReports.
 *
 * @phpstan-type Report array{columns: list<array{key: string, type: string}>, rows: list<array<string, string|null>>, totals: array<string, string>}
 */
class FollowUpReports
{
    public const REPORTS = ['recon', 'mapprovals', 'uncomp', 'xreserve', 'advances', 'loans'];

    public const ADVANCES_ACCOUNT = '1201';

    /** @return Report */
    public function run(string $report, ?FollowUpFilters $filters = null, ?CarbonImmutable $today = null): array
    {
        $filters ??= new FollowUpFilters;
        $today ??= CarbonImmutable::today();

        return match ($report) {
            'recon' => $this->reconciliation($filters, $today),
            'mapprovals' => $this->monthlyApprovals($filters, $today),
            'uncomp' => $this->uncompensated($filters, $today),
            'xreserve' => $this->exchangeReserve($filters),
            'advances' => $this->advances($filters),
            'loans' => $this->loans($filters, $today),
            default => abort(404),
        };
    }

    /** Approved lines up to the "to" date (the "from" date is each report's business), by city and project. */
    private function lines(FollowUpFilters $filters): Builder
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->leftJoin('gl_projects as p', 'p.code', '=', 'l.project_code')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at')
            ->when($filters->to, fn (Builder $query, string $value) => $query->whereDate('v.date', '<=', $value))
            ->when($filters->city, fn (Builder $query, string $value) => $query->whereRaw('coalesce(l.city, p.city) = ?', [$value]))
            ->when($filters->project, fn (Builder $query, string $value) => $query->where('l.project_code', $value))
            ->when($filters->limitedTo !== null, fn (Builder $query) => $query->whereIn('l.project_code', $filters->limitedTo ?? []));
    }

    /**
     * The pool is every 1161 line; "projects" is what lines on a project hold, "general" what lines on a city general
     * account hold, and the difference is what sits in the pool with no project at all. Balances are at each month's end.
     * A city general account with a negative balance in the pool is allowed (R4-8) but listed in `negative` and the row is flagged.
     *
     * @return Report
     */
    private function reconciliation(FollowUpFilters $filters, CarbonImmutable $today): array
    {
        $lines = $this->lines($filters)->where('l.account_code', (string) config('ledger.transfer_company_account'))
            ->orderBy('v.date')
            ->get(['v.date', 'l.project_code', 'p.is_general_account as general', 'l.debit_usd', 'l.credit_usd']);

        $first = $lines->isEmpty() ? null : CarbonImmutable::parse(substr((string) $lines->first()->date, 0, 10))->startOfMonth();
        $start = $filters->from !== null ? CarbonImmutable::parse($filters->from)->startOfMonth() : $first;
        $end = CarbonImmutable::parse($filters->to ?? $today->toDateString())->startOfMonth();
        $rows = [];

        for ($month = $start; $month !== null && $month <= $end; $month = $month->addMonth()) {
            $monthEnd = $month->endOfMonth()->toDateString();
            $pool = $projects = $general = Money::zero();
            $byGeneral = [];
            foreach ($lines as $line) {
                if (substr((string) $line->date, 0, 10) > $monthEnd) {
                    break;
                }
                $net = bcsub(Money::normalize($line->debit_usd), Money::normalize($line->credit_usd), 2);
                $pool = bcadd($pool, $net, 2);
                if ($line->project_code !== null && $line->general) {
                    $general = bcadd($general, $net, 2);
                    $byGeneral[$line->project_code] = bcadd($byGeneral[$line->project_code] ?? '0.00', $net, 2);
                } elseif ($line->project_code !== null) {
                    $projects = bcadd($projects, $net, 2);
                }
            }
            $rows[] = ['month' => $month->format('Y-m'), 'pool' => $pool, 'projects' => $projects, 'general' => $general, 'difference' => bcsub($pool, bcadd($projects, $general, 2), 2),
                'negative' => implode(', ', array_keys(array_filter($byGeneral, fn (string $balance) => bccomp($balance, '0', 2) < 0))), 'negative_flag' => ''];
            $rows[array_key_last($rows)]['negative_flag'] = $rows[array_key_last($rows)]['negative'];
        }

        return [
            'columns' => [['key' => 'month', 'type' => 'ltr'], ['key' => 'pool', 'type' => 'money'], ['key' => 'projects', 'type' => 'money'], ['key' => 'general', 'type' => 'money'], ['key' => 'difference', 'type' => 'money'], ['key' => 'negative', 'type' => 'text']],
            'rows' => $rows,
            'totals' => [],
        ];
    }

    /**
     * Monthly approvals: one month (the month of the "from" date, else of the "to" date, else of today), one row per project.
     * `usd_start` and `usd_end` are the project's balance on 1161 (debits less credits, USD) before the month and at its end,
     * from approved vouchers. `syp_approved` and `usd_approved` are what approved requests (final decision in the month)
     * asked for, in pounds and in dollars; `syp_cost` is what the pound allocations of the month moved on 1161 (the dollar value
     * the project repays). `other` is every other movement on 1161, so start + cost + other = end exactly. `syp_start` and
     * `syp_end` are the pounds the project holds in cash.
     *
     * @return Report
     */
    private function monthlyApprovals(FollowUpFilters $filters, CarbonImmutable $today): array
    {
        $anchor = CarbonImmutable::parse($filters->from ?? $filters->to ?? $today->toDateString())->startOfMonth();
        $start = $anchor->toDateString();
        $end = $anchor->endOfMonth()->toDateString();
        $scope = new FollowUpFilters(null, $end, $filters->city, $filters->project, $filters->limitedTo);
        $rows = [];

        $pool = $this->lines($scope)->where('l.account_code', (string) config('ledger.transfer_company_account'))
            ->get(['v.date', 'v.transfer_kind', 'l.project_code', 'l.debit_usd', 'l.credit_usd']);

        foreach ($pool as $line) {
            $code = (string) ($line->project_code ?? '');
            $rows[$code] ??= $this->blankApprovalRow($code);
            $net = bcsub(Money::normalize($line->debit_usd), Money::normalize($line->credit_usd), 2);
            $rows[$code]['usd_end'] = bcadd($rows[$code]['usd_end'], $net, 2);

            if (substr((string) $line->date, 0, 10) < $start) {
                $rows[$code]['usd_start'] = bcadd($rows[$code]['usd_start'], $net, 2);
            } elseif ($line->transfer_kind === TransferKind::SypToProject->value) {
                $rows[$code]['syp_cost'] = bcadd($rows[$code]['syp_cost'], $net, 2);
            }
        }

        $cash = $this->lines($scope)->join('gl_accounts as a', 'a.code', '=', 'l.account_code')->where('a.is_cash', true)->where('l.currency', 'SYP')
            ->get(['v.date', 'l.project_code', 'l.debit_orig', 'l.credit_orig']);

        foreach ($cash as $line) {
            $code = (string) ($line->project_code ?? '');
            $rows[$code] ??= $this->blankApprovalRow($code);
            $net = bcsub(Money::normalize($line->debit_orig), Money::normalize($line->credit_orig), 2);
            $rows[$code]['syp_end'] = bcadd($rows[$code]['syp_end'], $net, 2);

            if (substr((string) $line->date, 0, 10) < $start) {
                $rows[$code]['syp_start'] = bcadd($rows[$code]['syp_start'], $net, 2);
            }
        }

        $requests = ! Schema::hasTable('gl_request_lines') ? collect() : DB::table('gl_request_lines as rl')
            ->join('gl_requests as r', 'r.id', '=', 'rl.request_id')
            ->leftJoin('gl_projects as p', 'p.code', '=', 'r.project_code')
            ->whereIn('r.status', ['approved', 'closed'])
            ->whereDate('r.decided_at', '>=', $start)->whereDate('r.decided_at', '<=', $end)
            ->when($filters->city, fn (Builder $query, string $value) => $query->where('p.city', $value))
            ->when($filters->project, fn (Builder $query, string $value) => $query->where('r.project_code', $value))
            ->when($filters->limitedTo !== null, fn (Builder $query) => $query->whereIn('r.project_code', $filters->limitedTo ?? []))
            ->get(['r.project_code', 'rl.currency', 'rl.amount', 'rl.amount_usd']);

        foreach ($requests as $line) {
            $code = (string) $line->project_code;
            $rows[$code] ??= $this->blankApprovalRow($code);
            $key = $line->currency === 'SYP' ? 'syp_approved' : 'usd_approved';
            $rows[$code][$key] = bcadd($rows[$code][$key], Money::normalize($line->currency === 'SYP' ? $line->amount : $line->amount_usd), 2);
        }

        foreach ($rows as $code => $values) {
            $rows[$code]['other'] = bcsub(bcsub($values['usd_end'], $values['usd_start'], 2), $values['syp_cost'], 2);

            if (count(array_filter(array_slice($rows[$code], 1), fn (string $amount) => bccomp($amount, '0', 2) !== 0)) === 0) {
                unset($rows[$code]);
            }
        }
        ksort($rows);

        $keys = ['usd_start', 'syp_approved', 'syp_cost', 'usd_approved', 'other', 'usd_end', 'syp_start', 'syp_end'];

        return [
            'columns' => [['key' => 'project', 'type' => 'ltr'], ...array_map(fn (string $key) => ['key' => $key, 'type' => 'money'], $keys)],
            'rows' => array_values($rows),
            'totals' => $this->sum($rows, $keys),
        ];
    }

    /** @return array{project: string, usd_start: numeric-string, syp_approved: numeric-string, syp_cost: numeric-string, usd_approved: numeric-string, other: numeric-string, usd_end: numeric-string, syp_start: numeric-string, syp_end: numeric-string} */
    private function blankApprovalRow(string $code): array
    {
        $zero = Money::zero();

        return ['project' => $code, 'usd_start' => $zero, 'syp_approved' => $zero, 'syp_cost' => $zero, 'usd_approved' => $zero, 'other' => $zero, 'usd_end' => $zero, 'syp_start' => $zero, 'syp_end' => $zero];
    }

    /**
     * Lines flagged `is_uncompensated` (the flag is cleared when projects compensate the general account) by city and age
     * in days from the voucher date to the "to" date or today. No alert, only the buckets.
     *
     * @return Report
     */
    private function uncompensated(FollowUpFilters $filters, CarbonImmutable $today): array
    {
        $lines = $this->lines($filters)->where('l.is_uncompensated', true)
            ->when($filters->from, fn (Builder $query, string $value) => $query->whereDate('v.date', '>=', $value))
            ->get(['v.date', 'l.city', 'p.city as project_city', 'l.debit_usd', 'l.credit_usd']);

        $asAt = CarbonImmutable::parse($filters->to ?? $today->toDateString());
        $cities = [];
        foreach ($lines as $line) {
            $city = (string) ($line->city ?? $line->project_city ?? '');
            $age = (int) CarbonImmutable::parse(substr((string) $line->date, 0, 10))->diffInDays($asAt);
            $bucket = $age < 30 ? 'under_30' : ($age <= 90 ? 'days_30_90' : 'over_90');
            $cities[$city] ??= ['city' => $city, 'under_30' => Money::zero(), 'days_30_90' => Money::zero(), 'over_90' => Money::zero(), 'total' => Money::zero()];
            $net = bcsub(Money::normalize($line->debit_usd), Money::normalize($line->credit_usd), 2);
            $cities[$city][$bucket] = bcadd($cities[$city][$bucket], $net, 2);
            $cities[$city]['total'] = bcadd($cities[$city]['total'], $net, 2);
        }
        ksort($cities);

        return [
            'columns' => [['key' => 'city', 'type' => 'text'], ['key' => 'under_30', 'type' => 'money'], ['key' => 'days_30_90', 'type' => 'money'], ['key' => 'over_90', 'type' => 'money'], ['key' => 'total', 'type' => 'money']],
            'rows' => array_values($cities),
            'totals' => $this->sum($cities, ['under_30', 'days_30_90', 'over_90', 'total']),
        ];
    }

    /**
     * 5503 lines of approved transfer vouchers (SYP allocation, local exchange) on a city's general SYP project, per city: gains
     * (credits), losses (debits) and the reserve they leave, up to the "to" date.
     *
     * @return Report
     */
    private function exchangeReserve(FollowUpFilters $filters): array
    {
        $lines = $this->lines($filters)
            ->where('l.account_code', (string) config('ledger.rounding_account'))
            ->where('p.is_general_account', true)->where('l.project_code', 'like', '%-SYP')
            ->whereIn('v.transfer_kind', [TransferKind::SypToProject->value, TransferKind::LocalExchange->value])
            ->when($filters->from, fn (Builder $query, string $value) => $query->whereDate('v.date', '>=', $value))
            ->get(['l.city', 'p.city as project_city', 'l.debit_usd', 'l.credit_usd']);

        $cities = [];
        foreach ($lines as $line) {
            $city = (string) ($line->city ?? $line->project_city ?? '');
            $cities[$city] ??= ['city' => $city, 'gains' => Money::zero(), 'losses' => Money::zero(), 'reserve' => Money::zero()];
            $cities[$city]['gains'] = bcadd($cities[$city]['gains'], Money::normalize($line->credit_usd), 2);
            $cities[$city]['losses'] = bcadd($cities[$city]['losses'], Money::normalize($line->debit_usd), 2);
            $cities[$city]['reserve'] = bcsub($cities[$city]['gains'], $cities[$city]['losses'], 2);
        }
        ksort($cities);

        return [
            'columns' => [['key' => 'city', 'type' => 'text'], ['key' => 'gains', 'type' => 'money'], ['key' => 'losses', 'type' => 'money'], ['key' => 'reserve', 'type' => 'money']],
            'rows' => array_values($cities),
            'totals' => $this->sum($cities, ['gains', 'losses', 'reserve']),
        ];
    }

    /**
     * Account 1201 by the contact (person) tag, up to the "to" date: advanced, settled and what is still open. A person
     * who settled with more than the advance shows a negative open balance, the difference to reconcile with them.
     *
     * @return Report
     */
    private function advances(FollowUpFilters $filters): array
    {
        $sums = $this->lines($filters)->where('l.account_code', self::ADVANCES_ACCOUNT)
            ->when($filters->from, fn (Builder $query, string $value) => $query->whereDate('v.date', '>=', $value))
            ->groupBy('l.contact')->orderBy('l.contact')
            ->selectRaw('l.contact as person, sum(l.debit_usd) as advanced, sum(l.credit_usd) as settled, max(v.date) as last_date')
            ->get();

        $people = [];
        foreach ($sums as $sum) {
            $advanced = Money::normalize($sum->advanced);
            $settled = Money::normalize($sum->settled);
            $open = bcsub($advanced, $settled, 2);
            if (bccomp($open, '0', 2) === 0) {
                continue;
            }
            $people[] = ['person' => (string) $sum->person, 'advanced' => $advanced, 'settled' => $settled, 'open' => $open, 'last_date' => substr((string) $sum->last_date, 0, 10)];
        }

        return [
            'columns' => [['key' => 'person', 'type' => 'text'], ['key' => 'advanced', 'type' => 'money'], ['key' => 'settled', 'type' => 'money'], ['key' => 'open', 'type' => 'money'], ['key' => 'last_date', 'type' => 'date']],
            'rows' => $people,
            'totals' => $this->sum($people, ['advanced', 'settled', 'open']),
        ];
    }

    /**
     * Per micro loan, in the loan's own currency: the instalments due in the date range (up to the "to" date, or today),
     * what approved vouchers collected of them, and the arrears (due and not collected).
     *
     * @return Report
     */
    private function loans(FollowUpFilters $filters, CarbonImmutable $today): array
    {
        $asAt = $filters->to ?? $today->toDateString();

        $loans = DB::table('gl_micro_loans as m')
            ->leftJoin('gl_projects as p', 'p.code', '=', 'm.project_code')
            ->when($filters->city, fn (Builder $query, string $value) => $query->where('p.city', $value))
            ->when($filters->project, fn (Builder $query, string $value) => $query->where('m.project_code', $value))
            ->when($filters->limitedTo !== null, fn (Builder $query) => $query->whereIn('m.project_code', $filters->limitedTo ?? []))
            ->orderBy('m.project_code')->orderBy('m.beneficiary')->orderBy('m.id')
            ->get(['m.id', 'm.beneficiary', 'm.project_code', 'm.loan_amount', 'm.grant_amount', 'm.currency']);

        $instalments = DB::table('gl_micro_instalments as i')
            ->leftJoin('gl_vouchers as v', 'v.id', '=', 'i.paid_voucher_id')
            ->whereIn('i.loan_id', $loans->pluck('id')->all())
            ->when($filters->from, fn (Builder $query, string $value) => $query->whereDate('i.due_date', '>=', $value))
            ->whereDate('i.due_date', '<=', $asAt)
            ->get(['i.loan_id', 'i.due_date', 'i.amount', 'i.paid_amount', 'v.status', 'v.deleted_at'])
            ->groupBy('loan_id');

        $rows = [];
        foreach ($loans as $loan) {
            $due = $collected = Money::zero();
            $late = 0;
            foreach ($instalments->get($loan->id, []) as $instalment) {
                $paid = $instalment->status === VoucherStatus::Approved->value && $instalment->deleted_at === null ? Money::normalize($instalment->paid_amount) : Money::zero();
                $due = bcadd($due, Money::normalize($instalment->amount), 2);
                $collected = bcadd($collected, $paid, 2);

                // Late, as in the Windows program: due before the date and not fully paid.
                if (substr((string) $instalment->due_date, 0, 10) < $asAt && bccomp(Money::normalize($instalment->amount), $paid, 2) > 0) {
                    $late++;
                }
            }
            $rows[] = [
                'beneficiary' => $loan->beneficiary, 'project' => $loan->project_code, 'currency' => $loan->currency,
                'loan' => Money::normalize($loan->loan_amount), 'grant' => Money::normalize($loan->grant_amount),
                'due' => $due, 'collected' => $collected, 'arrears' => bcsub($due, $collected, 2),
                'late' => (string) $late, 'late_flag' => $late >= MicroLoans::LATE_FLAG ? '1' : '',
            ];
        }

        // Amounts in different currencies are not added together.
        $single = count(array_unique(array_column($rows, 'currency'))) <= 1;

        return [
            'columns' => [['key' => 'beneficiary', 'type' => 'text'], ['key' => 'project', 'type' => 'ltr'], ['key' => 'currency', 'type' => 'ltr'], ['key' => 'loan', 'type' => 'money'], ['key' => 'grant', 'type' => 'money'], ['key' => 'due', 'type' => 'money'], ['key' => 'collected', 'type' => 'money'], ['key' => 'arrears', 'type' => 'money'], ['key' => 'late', 'type' => 'number']],
            'rows' => $rows,
            'totals' => $single ? $this->sum($rows, ['loan', 'grant', 'due', 'collected', 'arrears']) : [],
        ];
    }

    /**
     * @param  array<array-key, array<string, string|null>>  $rows
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function sum(array $rows, array $keys): array
    {
        if ($rows === []) {
            return [];
        }

        $totals = [];
        foreach ($keys as $key) {
            $totals[$key] = array_reduce($rows, fn (string $carry, array $row) => bcadd($carry, Money::normalize($row[$key]), 2), Money::zero());
        }

        return $totals;
    }
}
