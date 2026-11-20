<?php

namespace App\Services\Treasury;

use App\Models\DailyRate;
use App\Models\User;
use App\Services\Ledger\Currencies;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The daily exchange rates of the organisation (finance decision of 2026-11-19, one treasury): one rate per currency
 * and day, for every city. An accountant proposes it and the Financial Manager approves or rejects it, never the person
 * who proposed it. A rate typed by someone who may approve (the Financial Manager) is approved at once. A day with no
 * rate of its own uses the latest approved rate before it, and says so.
 */
class DailyRates
{
    public function __construct(private readonly Currencies $currencies) {}

    /** @return list<string> the currencies that need a daily rate: every active one but the dollar */
    public function currencies(): array
    {
        return $this->currencies->foreign();
    }

    /**
     * @param  bool  $approveNow  the person may approve rates, so theirs needs no second look
     *
     * @throws TreasuryException
     */
    public function propose(string $currency, string $day, string $rate, ?string $note, User $by, bool $approveNow): DailyRate
    {
        $problems = [];

        if (! in_array($currency, $this->currencies(), true)) {
            $problems[] = __('treasury.rate_bad_currency');
        }

        $date = $this->day($day);
        if ($date === null) {
            $problems[] = __('treasury.bad_date');
        }

        $value = $this->number($rate, 6);
        if ($value === null || bccomp($value, '0', 6) !== 1) {
            $problems[] = __('treasury.rate_bad_rate');
        }

        if ($problems !== []) {
            throw new TreasuryException($problems);
        }

        /** @var CarbonImmutable $date */
        return DB::transaction(function () use ($currency, $date, $value, $note, $by, $approveNow): DailyRate {
            $proposed = DailyRate::query()->create([
                'day' => $date->toDateString(),
                'currency' => $currency,
                'rate' => $value,
                'status' => DailyRate::PENDING,
                'note' => trim((string) $note) ?: null,
                'proposed_by' => (int) $by->getKey(),
            ]);

            return $approveNow ? $this->settle($proposed, $by, DailyRate::APPROVED) : $proposed;
        });
    }

    /** @throws TreasuryException */
    public function approve(int $id, User $by): DailyRate
    {
        return $this->decide($id, $by, DailyRate::APPROVED);
    }

    /** @throws TreasuryException */
    public function reject(int $id, User $by): DailyRate
    {
        return $this->decide($id, $by, DailyRate::REJECTED);
    }

    /** The approved rate of a currency on a day: that day's own, else the latest approved before it; null when there is none. */
    public function rateFor(string $currency, CarbonImmutable $day): ?DailyRate
    {
        return DailyRate::query()
            ->where('currency', $currency)
            ->where('status', DailyRate::APPROVED)
            ->whereDate('day', '<=', $day->toDateString())
            ->orderByDesc('day')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The dollar value of an amount at the day's approved rate; a dollar amount is itself.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     *
     * @throws TreasuryException when the currency has no approved rate on or before that day
     */
    public function toUsd(string $currency, string $amount, CarbonImmutable $day): string
    {
        if ($currency === Currencies::BASE) {
            return Money::normalize($amount);
        }

        return $this->currencies->toUsd($currency, $amount, $this->requireRate($currency, $day));
    }

    /**
     * @return numeric-string
     *
     * @throws TreasuryException
     */
    public function requireRate(string $currency, CarbonImmutable $day): string
    {
        $rate = $this->rateFor($currency, $day);

        if ($rate === null) {
            throw new TreasuryException([__('treasury.no_rate', ['currency' => $currency, 'date' => $day->format('d/m/Y')])]);
        }

        return Money::normalize($rate->rate, 6);
    }

    /**
     * Removes a rate (finance request of 2026-11-20): the person who proposed a waiting rate may take it back, and the
     * Financial Manager may remove any rate. It stays in the history as removed; a day without an approved rate takes
     * the latest one before it. The vouchers already made keep the rate they were made at.
     *
     * @param  bool  $mayApprove  the person approves rates
     *
     * @throws TreasuryException
     */
    public function remove(int $id, User $by, bool $mayApprove): DailyRate
    {
        $rate = DailyRate::query()->whereKey($id)->first();

        if ($rate === null || ! in_array($rate->status, [DailyRate::PENDING, DailyRate::APPROVED], true)) {
            throw new TreasuryException([__('treasury.rate_not_removable')]);
        }

        $own = $rate->status === DailyRate::PENDING && $rate->proposed_by === (int) $by->getKey();

        if (! $own && ! $mayApprove) {
            throw new TreasuryException([__('treasury.rate_remove_not_allowed')]);
        }

        $rate->update(['status' => DailyRate::REMOVED, 'decided_by' => (int) $by->getKey(), 'decided_at' => now()]);

        return $rate;
    }

    private function decide(int $id, User $by, string $status): DailyRate
    {
        $rate = DailyRate::query()->whereKey($id)->first();

        if ($rate === null || $rate->status !== DailyRate::PENDING) {
            throw new TreasuryException([__('treasury.rate_not_pending')]);
        }

        if ($rate->proposed_by === (int) $by->getKey()) {
            throw new TreasuryException([__('treasury.rate_own')]);
        }

        return DB::transaction(fn (): DailyRate => $this->settle($rate, $by, $status));
    }

    /** Records the decision; an approved rate replaces the one approved before it for the same day and currency. */
    private function settle(DailyRate $rate, User $by, string $status): DailyRate
    {
        if ($status === DailyRate::APPROVED) {
            DailyRate::query()
                ->where('currency', $rate->currency)
                ->whereDate('day', $rate->day->toDateString())
                ->where('status', DailyRate::APPROVED)
                ->whereKeyNot($rate->id)
                ->update(['status' => DailyRate::REPLACED]);
        }

        $rate->update(['status' => $status, 'decided_by' => (int) $by->getKey(), 'decided_at' => now()]);

        return $rate;
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }

    /** @return numeric-string|null */
    private function number(string $text, int $scale): ?string
    {
        try {
            return Money::normalize(str_replace([',', ' '], '', trim($text)), $scale);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
