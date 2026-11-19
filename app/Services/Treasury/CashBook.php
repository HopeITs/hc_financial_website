<?php

namespace App\Services\Treasury;

use App\Enums\AccountLevel;
use App\Enums\VoucherStatus;
use App\Models\GlAccount;
use App\Services\Ledger\Currencies;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cash and bank accounts as the books hold them (finance decisions of 2026-11-19 and 2026-11-20): what each holds
 * at the end of a day in its own currency and in book dollars, from every voucher that stands (approved, and pending,
 * since the money already moved), with the pending part apart; and what that is worth today. Pounds are valued at the
 * month's rate, any other currency at the day's approved rate.
 */
class CashBook
{
    public function __construct(
        private readonly MonthlyRates $monthly,
        private readonly DailyRates $daily,
        private readonly Currencies $currencies,
    ) {}

    /** @return Collection<string, GlAccount> the active cash and bank accounts, by code */
    public function accounts(): Collection
    {
        return GlAccount::query()->where('is_cash', true)->where('is_active', true)->where('level', AccountLevel::Posting->value)
            ->orderBy('code')->get()->keyBy('code');
    }

    /**
     * @return array<string, array{orig: string, usd: string, pending: string}> account code => balance in its currency, book dollars, and the part still pending approval
     */
    public function balances(CarbonImmutable $asAt): array
    {
        $rows = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('a.is_cash', true)
            ->where('v.status', '!=', VoucherStatus::Replaced->value)
            ->whereNull('v.deleted_at')
            ->whereDate('v.date', '<=', $asAt->toDateString())
            ->groupBy('l.account_code')
            ->selectRaw('l.account_code as code, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_usd) - sum(l.credit_usd) as usd, sum(case when v.status = ? then l.debit_orig - l.credit_orig else 0 end) as pending', [VoucherStatus::PendingApproval->value])
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $balances[(string) $row->code] = ['orig' => $this->money($row->orig), 'usd' => $this->money($row->usd), 'pending' => $this->money($row->pending)];
        }

        return $balances;
    }

    /**
     * The rate a currency is valued at on a day: the month's for pounds, the day's approved rate for any other; null for
     * dollars and when there is none.
     *
     * @return numeric-string|null
     */
    public function rateFor(string $currency, CarbonImmutable $day): ?string
    {
        if ($currency === Currencies::BASE) {
            return null;
        }

        if ($currency === 'SYP') {
            return $this->monthly->forDay($currency, $day)['rate'] ?? null;
        }

        $rate = $this->daily->rateFor($currency, $day);

        return $rate !== null ? Money::normalize($rate->rate, 6) : null;
    }

    /**
     * Where the money is on a day, grouped: the banks, the transfer company, then each city's safes and tills.
     *
     * @return array{groups: list<array{key: string, rows: list<array{code: string, name: string, currency: string, orig: string, usd: string, value: string|null, pending: string}>, value: string}>, total_usd: string, total_value: string, missing_rates: list<string>}
     */
    public function position(CarbonImmutable $asAt): array
    {
        $balances = $this->balances($asAt);
        $arabic = app()->getLocale() === 'ar';
        $company = (string) config('ledger.transfer_company_account');
        $groups = [];
        $totalUsd = Money::zero();
        $totalValue = Money::zero();
        $missing = [];
        $rates = [];

        foreach ($this->accounts() as $account) {
            $balance = $balances[$account->code] ?? null;
            if ($balance === null || (bccomp($balance['orig'], '0', 2) === 0 && bccomp($balance['usd'], '0', 2) === 0)) {
                continue;
            }

            $currency = (string) $account->currency;
            $rates[$currency] ??= $this->rateFor($currency, $asAt);
            $value = $currency === Currencies::BASE ? $balance['orig'] : ($rates[$currency] !== null ? $this->currencies->toUsd($currency, $balance['orig'], $rates[$currency]) : null);

            if ($value === null) {
                $missing[$currency] = $currency;
            }

            $key = match (true) {
                $account->code === $company => 'company',
                $account->city === null || $account->city === '' => 'banks',
                default => 'city:'.$account->city,
            };

            $groups[$key] ??= ['key' => $key, 'rows' => [], 'value' => Money::zero()];
            $groups[$key]['rows'][] = [
                'code' => $account->code,
                'name' => $arabic ? $account->name_ar : $account->name_en,
                'currency' => $currency,
                'orig' => $balance['orig'],
                'usd' => $balance['usd'],
                'value' => $value,
                'pending' => $balance['pending'],
            ];
            $groups[$key]['value'] = bcadd($groups[$key]['value'], $value ?? $balance['usd'], 2);
            $totalUsd = bcadd($totalUsd, $balance['usd'], 2);
            $totalValue = bcadd($totalValue, $value ?? $balance['usd'], 2);
        }

        uksort($groups, fn (string $a, string $b): int => [$this->order($a), $a] <=> [$this->order($b), $b]);

        return ['groups' => array_values($groups), 'total_usd' => $totalUsd, 'total_value' => $totalValue, 'missing_rates' => array_values($missing)];
    }

    private function order(string $key): int
    {
        return match ($key) {
            'banks' => 0,
            'company' => 1,
            default => 2,
        };
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return Money::normalize(number_format((float) $value, 2, '.', ''));
    }
}
