<?php

namespace App\Services\Treasury;

use App\Enums\AccountLevel;
use App\Enums\VoucherStatus;
use App\Models\GlAccount;
use App\Services\Ledger\Currencies;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The day's estimate of money kept in a currency other than the dollar and the pound (finance decision of 2026-11-21):
 * euros in a bank stay euros until they are withdrawn, and their dollars are only an estimate that follows the day's
 * approved rate. Nothing is posted for it: the reports value the account at the day's rate, and the gap with its book
 * dollars shows as an unrealised exchange difference. When the euros are withdrawn they leave at their book dollars
 * and the real gain or loss is posted (5503), so an emptied account has no estimate left. Pounds are not estimated
 * this way: they are revalued each month (Revaluations).
 */
class DailyValuation
{
    /** Pounds are valued at the month's rate and revalued with a voucher each month. */
    public const MONTHLY_CURRENCY = 'SYP';

    public function __construct(
        private readonly DailyRates $daily,
        private readonly Currencies $currencies,
    ) {}

    /** Whether money in this currency is estimated at the day's rate rather than revalued each month. */
    public function isDailyValued(?string $currency): bool
    {
        return ! in_array((string) $currency, ['', 'Any', Currencies::BASE, self::MONTHLY_CURRENCY], true);
    }

    /**
     * Each such cash or bank account on a day, from approved vouchers: its amount, its book dollars, the day's rate, the
     * estimated dollars and the gap (zero when the day has no approved rate yet).
     *
     * @return array<string, array{currency: string, orig: string, usd: string, rate: string|null, value: string|null, difference: string}>
     */
    public function asAt(CarbonImmutable $day): array
    {
        $codes = GlAccount::query()->where('is_cash', true)->where('level', AccountLevel::Posting->value)->get(['code', 'currency'])
            ->filter(fn (GlAccount $account): bool => $this->isDailyValued($account->currency))
            ->pluck('currency', 'code');

        if ($codes->isEmpty()) {
            return [];
        }

        $rows = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at')
            ->whereDate('v.date', '<=', $day->toDateString())
            ->whereIn('l.account_code', $codes->keys()->all())
            ->groupBy('l.account_code')
            ->selectRaw('l.account_code as code, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        $rates = [];
        $valued = [];

        foreach ($rows as $row) {
            $orig = Money::normalize(number_format((float) $row->orig, 2, '.', ''));
            $usd = Money::normalize(number_format((float) $row->usd, 2, '.', ''));

            if (bccomp($orig, '0', 2) === 0 && bccomp($usd, '0', 2) === 0) {
                continue;
            }

            $currency = (string) $codes[$row->code];
            if (! array_key_exists($currency, $rates)) {
                $rate = $this->daily->rateFor($currency, $day);
                $rates[$currency] = $rate !== null ? Money::normalize($rate->rate, 6) : null;
            }

            $value = $rates[$currency] !== null ? $this->currencies->toUsd($currency, $orig, $rates[$currency]) : null;
            $valued[(string) $row->code] = [
                'currency' => $currency,
                'orig' => $orig,
                'usd' => $usd,
                'rate' => $rates[$currency],
                'value' => $value,
                'difference' => $value !== null ? bcsub($value, $usd, 2) : '0.00',
            ];
        }

        return $valued;
    }
}
