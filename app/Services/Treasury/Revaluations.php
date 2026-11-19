<?php

namespace App\Services\Treasury;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Revaluation;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\Currencies;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The month's revaluation (finance decisions of 2026-11-19, 2026-11-20 and 2026-11-21): at the end of a month, the
 * pounds held are valued again at the month's rate, and the difference with their book dollars goes to 5503, the
 * organisation's. Euros and other currencies are not: their dollars are an estimate at the day's rate that the reports
 * show without a voucher (DailyValuation), until they are withdrawn. Each account's balance is
 * taken out at its book dollars and put back at the new value (two lines on the account, same amount), so the account
 * keeps its amount and the voucher keeps every rule. It waits for approval; preparing it again books only what is left.
 */
class Revaluations
{
    public const EXCHANGE_DIFFERENCES = '5503';

    public function __construct(
        private readonly CashBook $book,
        private readonly MonthlyRates $monthly,
        private readonly PostVoucher $posting,
        private readonly Currencies $currencies,
        private readonly DailyValuation $valuation,
    ) {}

    /**
     * What the month's revaluation would book, account by account.
     *
     * @return array{end: string, rows: list<array{code: string, name: string, currency: string, orig: string, usd: string, rate: string|null, value: string|null, difference: string|null}>, difference: string, missing_rates: list<string>, provisional: list<string>, vouchers: list<array{id: string, status: VoucherStatus}>}
     */
    public function preview(string $month): array
    {
        $end = $this->endOf($month);
        $balances = $this->book->balances($end);
        $arabic = app()->getLocale() === 'ar';
        $rows = [];
        $total = Money::zero();
        $missing = [];
        $provisional = [];
        $rates = [];

        foreach ($this->book->accounts() as $account) {
            $currency = (string) $account->currency;
            $balance = $balances[$account->code] ?? null;

            // Euros and the like are not revalued with a voucher: the reports estimate them at the day's rate (DailyValuation).
            if ($currency === Currencies::BASE || $this->valuation->isDailyValued($currency) || $balance === null || bccomp($balance['orig'], '0', 2) !== 1) {
                continue;
            }

            $rates[$currency] ??= $this->book->rateFor($currency, $end);
            $rate = $rates[$currency];
            $value = $rate !== null ? $this->currencies->toUsd($currency, $balance['orig'], $rate) : null;
            $difference = $value !== null ? bcsub($value, $balance['usd'], 2) : null;

            if ($rate === null) {
                $missing[$currency] = $currency;
            } elseif ($currency === 'SYP' && ($this->monthly->forDay($currency, $end)['provisional'] ?? false)) {
                $provisional[$currency] = $currency;
            }

            $rows[] = [
                'code' => $account->code,
                'name' => $arabic ? $account->name_ar : $account->name_en,
                'currency' => $currency,
                'orig' => $balance['orig'],
                'usd' => $balance['usd'],
                'rate' => $rate,
                'value' => $value,
                'difference' => $difference,
            ];
            $total = bcadd($total, $difference ?? '0', 2);
        }

        $vouchers = Revaluation::query()->where('month', $month)->whereNotNull('voucher_id')->orderBy('id')->pluck('voucher_id')->all();
        $statuses = Voucher::query()->whereIn('id', $vouchers)->pluck('status', 'id');

        return [
            'end' => $end->toDateString(),
            'rows' => $rows,
            'difference' => $total,
            'missing_rates' => array_values($missing),
            'provisional' => array_values($provisional),
            'vouchers' => array_values(array_filter(array_map(fn (string $id): ?array => isset($statuses[$id]) && $statuses[$id] !== VoucherStatus::Replaced
                ? ['id' => $id, 'status' => $statuses[$id]] : null, $vouchers))),
        ];
    }

    /**
     * Prepares the month's revaluation voucher, dated the last day of the month.
     *
     * @throws TreasuryException
     */
    public function prepare(string $month, User $by): Voucher
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new TreasuryException([__('treasury.bad_month')]);
        }

        $preview = $this->preview($month);

        if ($preview['missing_rates'] !== []) {
            throw new TreasuryException(array_map(fn (string $currency): string => __('treasury.no_valuation_rate', ['currency' => $currency]), $preview['missing_rates']));
        }

        $accounts = $this->book->accounts();
        $lines = [];
        $total = Money::zero();

        foreach ($preview['rows'] as $row) {
            if ($row['difference'] === null || bccomp($row['difference'], '0', 2) === 0) {
                continue;
            }

            $account = $accounts[$row['code']];
            $center = in_array('Center', (array) $account->required_tags, true) ? $account->name_en : null;
            $bookRate = $this->currencies->rateOf($row['currency'], $row['orig'], $row['usd']) ?? (string) $row['rate'];

            $lines[] = new LineDraft($row['code'], $row['currency'], credit: $row['orig'], rate: $bookRate, creditUsd: $row['usd'], center: $center);
            $lines[] = new LineDraft($row['code'], $row['currency'], debit: $row['orig'], rate: (string) $row['rate'], debitUsd: (string) $row['value'], center: $center);
            $total = bcadd($total, $row['difference'], 2);
        }

        if ($lines === []) {
            throw new TreasuryException([__('treasury.reval_nothing')]);
        }

        $gain = bccomp($total, '0', 2) === 1;
        $size = Money::abs($total);
        $lines[] = new LineDraft(self::EXCHANGE_DIFFERENCES, Currencies::BASE, debit: $gain ? null : $size, credit: $gain ? $size : null,
            reason: __('treasury.reval_reason', ['month' => $month]));

        $draft = new VoucherDraft(
            type: VoucherType::Journal,
            date: CarbonImmutable::parse($preview['end']),
            preparedBy: (int) $by->getAuthIdentifier(),
            lines: $lines,
            description: __('treasury.reval_description', ['month' => CarbonImmutable::parse($month.'-01')->format('m/Y')]),
        );

        return DB::transaction(function () use ($draft, $month, $by): Voucher {
            try {
                $voucher = $this->posting->post($draft);
            } catch (LedgerRuleException $refused) {
                throw new TreasuryException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
            }

            Revaluation::query()->create(['month' => $month, 'voucher_id' => $voucher->id, 'prepared_by' => (int) $by->getKey()]);

            return $voucher;
        });
    }

    private function endOf(string $month): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1
            ? CarbonImmutable::parse($month.'-01')->endOfMonth()->startOfDay()
            : CarbonImmutable::today();
    }
}
