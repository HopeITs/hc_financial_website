<?php

namespace App\Services\Treasury;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\CashCount;
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
use InvalidArgumentException;

/**
 * The daily cash count (finance decision of 2026-11-19): each till, safe or bank is counted, or read from its
 * statement, at the end of the day and set against its book balance (every voucher that stands, pending included). A
 * difference needs an explanation, and the adjustment voucher books it on 5505 cash shortages and overages, an
 * organisation expense with no project: a shortage debits 5505, an overage credits it. It waits for approval.
 */
class CashCounts
{
    public const SHORTAGES = '5505';

    /** The city filter value for the accounts that belong to no city (the banks, the transfer company). */
    public const NO_CITY = '-';

    public function __construct(
        private readonly CashBook $book,
        private readonly PostVoucher $posting,
        private readonly Currencies $currencies,
    ) {}

    /** @return list<string> the cities of the cash accounts, then NO_CITY */
    public function cities(): array
    {
        $cities = $this->book->accounts()->pluck('city')->filter()->unique()->sort()->values()->all();

        return [...array_map('strval', $cities), self::NO_CITY];
    }

    /**
     * One row per cash account of the city on the day.
     *
     * @return list<array{code: string, name: string, currency: string, book: string, pending: string, counted: string|null, difference: string|null, explanation: string|null, voucher: string|null, voucher_status: VoucherStatus|null}>
     */
    public function rows(CarbonImmutable $day, string $city): array
    {
        $balances = $this->book->balances($day);
        $counts = CashCount::query()->whereDate('day', $day->toDateString())->get()->keyBy('account_code');
        $vouchers = Voucher::query()->whereIn('id', $counts->pluck('voucher_id')->filter()->values())->pluck('status', 'id');
        $arabic = app()->getLocale() === 'ar';
        $rows = [];

        foreach ($this->book->accounts() as $account) {
            $inCity = $city === '' || ($city === self::NO_CITY ? ($account->city === null || $account->city === '') : $account->city === $city);
            if (! $inCity) {
                continue;
            }

            $count = $counts->get($account->code);
            $balance = $balances[$account->code] ?? ['orig' => Money::zero(), 'pending' => Money::zero()];
            $status = $count?->voucher_id !== null ? ($vouchers[$count->voucher_id] ?? null) : null;

            $rows[] = [
                'code' => $account->code,
                'name' => $arabic ? $account->name_ar : $account->name_en,
                'currency' => (string) $account->currency,
                'book' => $balance['orig'],
                'pending' => $balance['pending'],
                'counted' => $count !== null ? (string) $count->counted : null,
                'difference' => $count !== null ? (string) $count->difference : null,
                'explanation' => $count?->explanation,
                'voucher' => $status !== null && $status !== VoucherStatus::Replaced ? $count?->voucher_id : null,
                'voucher_status' => $status !== VoucherStatus::Replaced ? $status : null,
            ];
        }

        return $rows;
    }

    /**
     * Saves a count against the book balance of that day.
     *
     * @throws TreasuryException
     */
    public function save(CarbonImmutable $day, string $code, string $counted, string $explanation, User $by): CashCount
    {
        $account = $this->book->accounts()->get($code);
        $amount = $this->number($counted);
        $existing = CashCount::query()->whereDate('day', $day->toDateString())->where('account_code', $code)->first();
        $problems = [];

        if ($account === null) {
            $problems[] = __('treasury.bad_account');
        }

        if ($amount === null || bccomp($amount, '0', 2) === -1) {
            $problems[] = __('treasury.count_bad_amount');
        }

        if ($existing !== null && $this->hasStandingVoucher($existing)) {
            $problems[] = __('treasury.count_locked', ['voucher' => $existing->voucher_id]);
        }

        if ($problems !== []) {
            throw new TreasuryException($problems);
        }

        /** @var numeric-string $amount */
        $book = $this->book->balances($day)[$code]['orig'] ?? Money::zero();

        return CashCount::query()->updateOrCreate(
            ['day' => $day->toDateString(), 'account_code' => $code],
            [
                'book' => $book,
                'counted' => $amount,
                'difference' => bcsub($amount, $book, 2),
                'explanation' => trim($explanation) ?: null,
                'voucher_id' => null,
                'counted_by' => (int) $by->getKey(),
            ],
        );
    }

    /**
     * Deletes a day's count of an account (finance request of 2026-11-20), while no adjustment voucher stands on it.
     *
     * @throws TreasuryException
     */
    public function remove(CarbonImmutable $day, string $code): void
    {
        $count = CashCount::query()->whereDate('day', $day->toDateString())->where('account_code', $code)->first();

        if ($count === null) {
            throw new TreasuryException([__('treasury.count_missing')]);
        }

        if ($this->hasStandingVoucher($count)) {
            throw new TreasuryException([__('treasury.count_locked', ['voucher' => $count->voucher_id])]);
        }

        $count->delete();
    }

    /** Whether a day's count of an account has an adjustment voucher that stands, so the count can no longer change. */
    public function isLocked(CarbonImmutable $day, string $code): bool
    {
        $count = CashCount::query()->whereDate('day', $day->toDateString())->where('account_code', $code)->first();

        return $count !== null && $this->hasStandingVoucher($count);
    }

    /**
     * Prepares the voucher that books a count's difference on 5505.
     *
     * @throws TreasuryException
     */
    public function adjust(CarbonImmutable $day, string $code, User $by): Voucher
    {
        $count = CashCount::query()->whereDate('day', $day->toDateString())->where('account_code', $code)->first();
        $account = $this->book->accounts()->get($code);

        if ($count === null || $account === null || bccomp((string) $count->difference, '0', 2) === 0) {
            throw new TreasuryException([__('treasury.count_nothing_to_adjust')]);
        }

        if ($this->hasStandingVoucher($count)) {
            throw new TreasuryException([__('treasury.count_locked', ['voucher' => $count->voucher_id])]);
        }

        if (trim((string) $count->explanation) === '') {
            throw new TreasuryException([__('treasury.count_needs_explanation')]);
        }

        $currency = (string) $account->currency;
        $rate = $currency === Currencies::BASE ? '1' : $this->book->rateFor($currency, $day);

        if ($rate === null) {
            throw new TreasuryException([__('treasury.no_valuation_rate', ['currency' => $currency])]);
        }

        $shortage = bccomp((string) $count->difference, '0', 2) === -1;
        $amount = Money::abs((string) $count->difference);
        $usd = $this->currencies->toUsd($currency, $amount, $rate);
        $tags = (array) $account->required_tags;

        $lines = [
            new LineDraft(self::SHORTAGES, $currency, debit: $shortage ? $amount : null, credit: $shortage ? null : $amount, rate: $rate,
                debitUsd: $shortage ? $usd : null, creditUsd: $shortage ? null : $usd, reason: (string) $count->explanation),
            new LineDraft($account->code, $currency, debit: $shortage ? null : $amount, credit: $shortage ? $amount : null, rate: $rate,
                debitUsd: $shortage ? null : $usd, creditUsd: $shortage ? $usd : null,
                contact: in_array('Contact', $tags, true) ? (string) config('ledger.transfer_company_contact') : null,
                center: in_array('Center', $tags, true) ? $account->name_en : null),
        ];

        $draft = new VoucherDraft(
            type: VoucherType::Journal,
            date: $day,
            preparedBy: (int) $by->getAuthIdentifier(),
            lines: $lines,
            description: __($shortage ? 'treasury.shortage_description' : 'treasury.overage_description', ['account' => $account->code, 'date' => $day->format('d/m/Y')]),
        );

        return DB::transaction(function () use ($draft, $count): Voucher {
            try {
                $voucher = $this->posting->post($draft);
            } catch (LedgerRuleException $refused) {
                throw new TreasuryException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
            }

            $count->update(['voucher_id' => $voucher->id]);

            return $voucher;
        });
    }

    private function hasStandingVoucher(CashCount $count): bool
    {
        if ($count->voucher_id === null) {
            return false;
        }

        $status = Voucher::query()->whereKey($count->voucher_id)->value('status');

        return $status !== null && $status !== VoucherStatus::Replaced;
    }

    /** @return numeric-string|null */
    private function number(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return Money::normalize(str_replace([',', ' '], '', trim($text)));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
