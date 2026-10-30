<?php

namespace App\Services\Ledger;

use App\Data\TreasuryCash;
use App\Data\TreasuryCity;
use App\Data\TreasuryReport;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where the money is, city by city, from approved vouchers only. Each city has two general accounts (projects
 * GEN-<city>-USD and GEN-<city>-SYP, Financial Manager decision R1-1): the dollars the first holds, and the pounds the
 * second holds with what they cost, so their average rate. Pounds are bought in bulk by a transfer (TRF) into the pound
 * safe. A city also has its safes by currency, the cash in transit (11x3, 11x4) and the exchange reserve (5503 on its
 * general accounts). Cash with no city (the transfer company, the banks) is listed apart.
 */
class TreasuryPosition
{
    public function at(?string $asAt = null): TreasuryReport
    {
        $balances = $this->cashBalances($asAt);
        $general = GlProject::query()->where('is_general_account', true)->orderBy('code')->get();
        $codes = array_values($general->map(fn (GlProject $project) => $project->code)->all());

        $holdings = $this->generalHoldings($asAt, $codes);
        $purchases = $this->purchases($asAt, array_values(array_filter($codes, fn (string $code) => str_ends_with($code, '-SYP'))));
        $reserve = $this->reserve($asAt, $codes);

        $cashAccounts = GlAccount::query()->where('is_cash', true)->where('level', 'posting')->orderBy('code')->get();
        $row = function (GlAccount $account) use ($balances): TreasuryCash {
            $balance = $balances[$account->code] ?? ['orig' => Money::zero(), 'usd' => Money::zero()];

            return new TreasuryCash($account->code, $account->name_en, $account->name_ar, (string) $account->currency, $balance['orig'], $balance['usd']);
        };
        $isInTransit = fn (GlAccount $account) => preg_match((string) config('ledger.in_transit_pattern'), $account->code) === 1;

        $cities = [];
        foreach ($general->groupBy('city')->sortKeys() as $city => $projects) {
            $usdProject = $this->project($projects, '-USD');
            $sypProject = $this->project($projects, '-SYP');
            $cityAccounts = $cashAccounts->filter(fn (GlAccount $account) => $account->city === $city);

            $held = $holdings[$sypProject ?? ''] ?? ['orig' => Money::zero(), 'usd' => Money::zero()];
            $bought = $purchases[$sypProject ?? ''] ?? ['pounds' => Money::zero(), 'usd' => Money::zero(), 'count' => 0, 'last' => null];

            $cities[] = new TreasuryCity(
                city: (string) $city,
                usdProject: $usdProject,
                sypProject: $sypProject,
                usdHeld: ($holdings[$usdProject ?? ''] ?? ['usd' => Money::zero()])['usd'],
                sypHeld: $held['orig'],
                sypBookUsd: $held['usd'],
                sypAverageRate: $this->rate($held['orig'], $held['usd']),
                purchasedSyp: $bought['pounds'],
                purchasedUsd: $bought['usd'],
                purchaseRate: $this->rate($bought['pounds'], $bought['usd']),
                purchaseCount: $bought['count'],
                lastPurchase: $bought['last'],
                safes: array_values($cityAccounts->reject($isInTransit)->map($row)->all()),
                inTransit: array_values($cityAccounts->filter($isInTransit)->map($row)->all()),
                reserve: bcadd(($reserve[$usdProject ?? ''] ?? Money::zero()), ($reserve[$sypProject ?? ''] ?? Money::zero()), 2),
            );
        }

        $other = array_values($cashAccounts->filter(fn (GlAccount $account) => $account->city === null || $account->city === '')->map($row)->all());

        return new TreasuryReport($asAt, $cities, $other);
    }

    /**
     * Net balance of every cash account, in its own currency and in USD.
     *
     * @return array<string, array{orig: numeric-string, usd: numeric-string}>
     */
    private function cashBalances(?string $asAt): array
    {
        $rows = $this->approved($asAt)
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('a.is_cash', true)
            ->groupBy('l.account_code')
            ->selectRaw('l.account_code as code, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $balances[(string) $row->code] = ['orig' => Money::normalize($row->orig), 'usd' => Money::normalize($row->usd)];
        }

        return $balances;
    }

    /**
     * What each general account holds in cash of its own currency: the pounds for a SYP account, the dollars for a USD one,
     * with the USD they stand for in the books.
     *
     * @param  list<string>  $codes
     * @return array<string, array{orig: numeric-string, usd: numeric-string}>
     */
    private function generalHoldings(?string $asAt, array $codes): array
    {
        $rows = $this->approved($asAt)
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('a.is_cash', true)
            ->whereIn('l.project_code', $codes)
            ->groupBy('l.project_code', 'a.currency')
            ->selectRaw('l.project_code as project, a.currency as currency, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        $holdings = [];
        foreach ($rows as $row) {
            if ($row->currency === (str_ends_with((string) $row->project, '-SYP') ? 'SYP' : 'USD')) {
                $holdings[(string) $row->project] = ['orig' => Money::normalize($row->orig), 'usd' => Money::normalize($row->usd)];
            }
        }

        return $holdings;
    }

    /**
     * Pounds bought in bulk: debits into a pound cash account on a transfer (TRF) or an exchange (EXG), by general SYP account.
     *
     * @param  list<string>  $sypCodes
     * @return array<string, array{pounds: numeric-string, usd: numeric-string, count: int, last: string|null}>
     */
    private function purchases(?string $asAt, array $sypCodes): array
    {
        $rows = $this->approved($asAt)
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->whereIn('v.type', [VoucherType::Transfer->value, VoucherType::Exchange->value])
            ->where('a.is_cash', true)
            ->where('a.currency', 'SYP')
            ->where('l.debit_orig', '>', 0)
            ->whereIn('l.project_code', $sypCodes)
            ->groupBy('l.project_code')
            ->selectRaw('l.project_code as project, sum(l.debit_orig) as pounds, sum(l.debit_usd) as usd, count(distinct l.voucher_id) as n, max(v.date) as last_date')
            ->get();

        $purchases = [];
        foreach ($rows as $row) {
            $purchases[(string) $row->project] = [
                'pounds' => Money::normalize($row->pounds),
                'usd' => Money::normalize($row->usd),
                'count' => (int) $row->n,
                'last' => substr((string) $row->last_date, 0, 10),
            ];
        }

        return $purchases;
    }

    /**
     * Exchange differences (5503) kept on the general accounts: credits minus debits, so a gain is positive.
     *
     * @param  list<string>  $codes
     * @return array<string, numeric-string>
     */
    private function reserve(?string $asAt, array $codes): array
    {
        $rows = $this->approved($asAt)
            ->where('l.account_code', (string) config('ledger.rounding_account'))
            ->whereIn('l.project_code', $codes)
            ->groupBy('l.project_code')
            ->selectRaw('l.project_code as project, sum(l.credit_usd) - sum(l.debit_usd) as usd')
            ->get();

        $reserve = [];
        foreach ($rows as $row) {
            $reserve[(string) $row->project] = Money::normalize($row->usd);
        }

        return $reserve;
    }

    /** The lines of approved vouchers up to the day (a null day means everything). */
    private function approved(?string $asAt): Builder
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at')
            ->when($asAt !== null, fn (Builder $query) => $query->whereDate('v.date', '<=', $asAt));
    }

    /** @param  Collection<int, GlProject>  $projects */
    private function project(Collection $projects, string $suffix): ?string
    {
        return $projects->first(fn (GlProject $project) => str_ends_with($project->code, $suffix))?->code;
    }

    /**
     * Pounds per dollar, or null when there are no pounds or no dollars to measure against.
     *
     * @param  numeric-string  $pounds
     * @param  numeric-string  $usd
     * @return numeric-string|null
     */
    private function rate(string $pounds, string $usd): ?string
    {
        return bccomp($pounds, '0', 2) > 0 && bccomp($usd, '0.01', 2) >= 0 ? bcdiv($pounds, $usd, 6) : null;
    }
}
