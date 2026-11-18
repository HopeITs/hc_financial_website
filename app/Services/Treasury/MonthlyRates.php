<?php

namespace App\Services\Treasury;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\MonthlyRate;
use App\Models\User;
use App\Services\Ledger\Currencies;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The monthly exchange rates (finance decision of 2026-11-19): one rate per currency and month for the whole
 * organisation, typed by the Financial Manager from what the dollars were really exchanged at. Every invoice and
 * expense of the month takes it by default and may be given another rate. Until a month has its own rate, the latest
 * month before it is used and called provisional. Changing a rate leaves the vouchers already made as they are; what
 * that leaves over goes to 5503 at the month end.
 */
class MonthlyRates
{
    public function __construct(private readonly Currencies $currencies) {}

    /** @throws TreasuryException */
    public function set(string $month, string $currency, string $rate, ?string $note, User $by): MonthlyRate
    {
        $problems = [];

        if (! in_array($currency, $this->currencies->foreign(), true)) {
            $problems[] = __('treasury.rate_bad_currency');
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            $problems[] = __('treasury.bad_month');
        }

        try {
            $value = Money::normalize(str_replace([',', ' '], '', trim($rate)), 6);
        } catch (InvalidArgumentException) {
            $value = '0';
        }

        if (bccomp($value, '0', 6) !== 1) {
            $problems[] = __('treasury.rate_bad_rate');
        }

        if ($problems !== []) {
            throw new TreasuryException($problems);
        }

        return MonthlyRate::query()->updateOrCreate(
            ['month' => $month, 'currency' => $currency],
            ['rate' => $value, 'note' => trim((string) $note) ?: null, 'set_by' => (int) $by->getKey()],
        );
    }

    /**
     * The rate an invoice or expense of that day takes: its month's own, else the latest month before it (provisional).
     *
     * @return array{rate: string, month: string, provisional: bool}|null
     */
    public function forDay(string $currency, CarbonImmutable $day): ?array
    {
        $month = $day->format('Y-m');
        $rate = MonthlyRate::query()->where('currency', $currency)->where('month', '<=', $month)->orderByDesc('month')->first();

        return $rate === null ? null : [
            'rate' => Money::normalize($rate->rate, 6),
            'month' => $rate->month,
            'provisional' => $rate->month !== $month,
        ];
    }

    /**
     * @return numeric-string
     *
     * @throws TreasuryException when no month up to that day has a rate
     */
    public function requireRate(string $currency, CarbonImmutable $day): string
    {
        return $this->forDay($currency, $day)['rate']
            ?? throw new TreasuryException([__('treasury.no_monthly_rate', ['currency' => $currency, 'month' => $day->format('m/Y')])]);
    }

    /**
     * What the dollars were really exchanged at in a month: every exchange voucher's lines that brought in the
     * currency (not replaced or deleted), as one rate in the currency's quoting, to help the Financial Manager.
     *
     * @return array{rate: string, amount: string, usd: string, count: int}|null
     */
    public function exchangedIn(string $currency, string $month): ?array
    {
        $row = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.type', VoucherType::Exchange->value)
            ->where('v.status', '!=', VoucherStatus::Replaced->value)
            ->whereNull('v.deleted_at')
            ->whereBetween('v.date', [$month.'-01', CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString()])
            ->where('l.currency', $currency)
            ->where('l.debit_orig', '>', 0)
            ->selectRaw('sum(l.debit_orig) as amount, sum(l.debit_usd) as usd, count(distinct l.voucher_id) as vouchers')
            ->first();

        $amount = Money::normalize(number_format((float) ($row->amount ?? 0), 2, '.', ''));
        $usd = Money::normalize(number_format((float) ($row->usd ?? 0), 2, '.', ''));
        $rate = $this->currencies->rateOf($currency, $amount, $usd);

        return $rate === null ? null : ['rate' => $rate, 'amount' => $amount, 'usd' => $usd, 'count' => (int) $row->vouchers];
    }
}
