<?php

namespace App\Services\Ledger;

use App\Enums\VoucherStatus;
use App\Models\GlAccount;
use App\Models\MonthClose;
use App\Models\MonthCloseLog;
use App\Models\MonthCount;
use App\Models\VoucherLine;
use App\Services\Ledger\Rules\MonthClosesOnlyWhenClearingIsZero;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Month-end close (F-09). Each cash account (a safe, the transfer company 1161, a bank 1171/1172; the cash in transit is
 * left to rule 12) is counted or read from its statement, and the count is set against the ledger balance at the end of the
 * month: approved vouchers only, in the account's own currency. A month closes only when every account that holds money
 * or was counted has a count whose difference is zero or has a written explanation, and rule 12 holds (the in-transit
 * accounts and 2901 are zero). Closing writes `gl_month_closes`, which rule 8 reads to refuse any voucher dated in the
 * month; reopening is logged with a reason.
 */
class MonthEnd
{
    public function __construct(private readonly MonthClosesOnlyWhenClearingIsZero $clearing = new MonthClosesOnlyWhenClearingIsZero) {}

    /** The city filter value for accounts that belong to no city (the transfer company and the banks). */
    public const NO_CITY = '-';

    /** @return list<string> the cities of the cash accounts, then NO_CITY if some belong to none */
    public function cities(): array
    {
        $cities = $this->accounts()->pluck('city')->unique()->values();
        $named = $cities->filter(fn ($city) => $city !== null && $city !== '')->sort()->values()->all();

        return [...$named, ...($cities->contains(fn ($city) => $city === null || $city === '') ? [self::NO_CITY] : [])];
    }

    /**
     * One row per cash account that holds money or was counted, at the end of the month.
     *
     * @param  string  $month  YYYY-MM
     * @param  string  $city  a city, NO_CITY, or '' for all
     * @return list<array{code: string, name_en: string, name_ar: string, city: string|null, currency: string, ledger: numeric-string, counted: numeric-string|null, difference: numeric-string|null, explanation: string|null, settled: bool}>
     */
    public function rows(string $month, string $city = ''): array
    {
        $ledger = $this->ledgerBalances($month);
        $counts = MonthCount::query()->where('month', $month)->get()->keyBy('account_code');
        $rows = [];

        foreach ($this->accounts() as $account) {
            $balance = $ledger[$account->code] ?? Money::zero();
            $count = $counts->get($account->code);

            if ($count === null && bccomp($balance, '0', 2) === 0) {
                continue;
            }

            if ($city !== '' && ($city === self::NO_CITY ? ($account->city !== null && $account->city !== '') : $account->city !== $city)) {
                continue;
            }

            $counted = $count !== null ? Money::normalize($count->counted) : null;
            $difference = $counted !== null ? bcsub($counted, $balance, 2) : null;
            $explanation = $count !== null && trim((string) $count->explanation) !== '' ? trim((string) $count->explanation) : null;

            $rows[] = [
                'code' => $account->code,
                'name_en' => $account->name_en,
                'name_ar' => $account->name_ar,
                'city' => $account->city,
                'currency' => (string) $account->currency,
                'ledger' => $balance,
                'counted' => $counted,
                'difference' => $difference,
                'explanation' => $explanation,
                'settled' => $difference !== null && (bccomp($difference, '0', 2) === 0 || $explanation !== null),
            ];
        }

        return $rows;
    }

    /**
     * Records what was counted for an account, and why it differs from the ledger if it does.
     *
     * @throws MonthEndException
     */
    public function saveCount(string $month, string $accountCode, string $counted, string $explanation, int $by): void
    {
        $this->assertMonth($month);

        if ($this->isClosed($month)) {
            throw new MonthEndException([__('ledger.monthend.closed', ['month' => $month])]);
        }

        if (! $this->accounts()->contains('code', $accountCode)) {
            throw new MonthEndException([__('ledger.monthend.bad_account')]);
        }

        try {
            $amount = Money::normalize(str_replace([',', ' '], '', trim($counted)));
        } catch (InvalidArgumentException) {
            throw new MonthEndException([__('ledger.monthend.bad_amount')]);
        }

        if (trim($counted) === '') {
            throw new MonthEndException([__('ledger.monthend.bad_amount')]);
        }

        MonthCount::query()->updateOrCreate(
            ['month' => $month, 'account_code' => $accountCode],
            ['counted' => $amount, 'explanation' => trim($explanation) !== '' ? trim($explanation) : null, 'entered_by' => $by],
        );
    }

    /**
     * What stops the month from closing: accounts with no count, differences with no explanation, rule 12.
     *
     * @return list<string>
     */
    public function blockers(string $month): array
    {
        $problems = [];

        foreach ($this->rows($month) as $row) {
            if ($row['counted'] === null) {
                $problems[] = __('ledger.monthend.not_counted', ['account' => $row['code']]);
            } elseif (! $row['settled']) {
                $problems[] = __('ledger.monthend.unexplained', ['account' => $row['code'], 'difference' => $row['difference']]);
            }
        }

        foreach ($this->clearing->check($month) as $violation) {
            $problems[] = $violation->message;
        }

        return $problems;
    }

    /** @throws MonthEndException */
    public function close(string $month, int $by): void
    {
        $this->assertMonth($month);

        DB::transaction(function () use ($month, $by) {
            if ($this->isClosed($month)) {
                throw new MonthEndException([__('ledger.monthend.closed', ['month' => $month])]);
            }

            $problems = $this->blockers($month);
            if ($problems !== []) {
                throw new MonthEndException($problems);
            }

            MonthClose::query()->create(['month' => $month, 'closed_at' => now(), 'closed_by' => $by]);
            MonthCloseLog::query()->create(['month' => $month, 'action' => 'closed', 'user_id' => $by]);
        });
    }

    /** @throws MonthEndException */
    public function reopen(string $month, int $by, string $reason): void
    {
        $this->assertMonth($month);

        if (trim($reason) === '') {
            throw new MonthEndException([__('ledger.monthend.reason_needed')]);
        }

        DB::transaction(function () use ($month, $by, $reason) {
            if (MonthClose::query()->whereKey($month)->delete() === 0) {
                throw new MonthEndException([__('ledger.monthend.not_closed', ['month' => $month])]);
            }

            MonthCloseLog::query()->create(['month' => $month, 'action' => 'reopened', 'user_id' => $by, 'reason' => trim($reason)]);
        });
    }

    public function isClosed(string $month): bool
    {
        return MonthClose::query()->whereKey($month)->exists();
    }

    /** @return Collection<int, MonthCloseLog> */
    public function history(string $month)
    {
        return MonthCloseLog::query()->where('month', $month)->orderBy('id')->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, GlAccount> the cash accounts that are counted: in transit left out */
    private function accounts()
    {
        return GlAccount::query()->where('is_cash', true)->where('level', 'posting')->where('is_active', true)->orderBy('code')->get()
            ->reject(fn (GlAccount $account) => preg_match((string) config('ledger.in_transit_pattern'), $account->code) === 1)
            ->values();
    }

    /** @return array<string, numeric-string> */
    private function ledgerBalances(string $month): array
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', $month)->endOfMonth()->toDateString();

        $sums = VoucherLine::query()
            ->join('gl_vouchers', 'gl_vouchers.id', '=', 'gl_voucher_lines.voucher_id')
            ->where('gl_vouchers.status', VoucherStatus::Approved->value)
            ->whereNull('gl_vouchers.deleted_at')
            ->where('gl_vouchers.date', '<=', $end)
            ->whereIn('gl_voucher_lines.account_code', $this->accounts()->pluck('code')->all())
            ->groupBy('gl_voucher_lines.account_code')
            ->selectRaw('gl_voucher_lines.account_code as code, sum(debit_orig) - sum(credit_orig) as orig')
            ->toBase()
            ->get();

        $balances = [];
        foreach ($sums as $row) {
            $balances[(string) $row->code] = Money::normalize(is_numeric($row->orig) ? (string) $row->orig : null);
        }

        return $balances;
    }

    /** @throws MonthEndException */
    private function assertMonth(string $month): void
    {
        $day = preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? CarbonImmutable::createFromFormat('!Y-m', $month) : false;

        if (! $day instanceof CarbonImmutable || $day->format('Y-m') !== $month) {
            throw new MonthEndException([__('ledger.monthend.bad_month')]);
        }
    }
}
