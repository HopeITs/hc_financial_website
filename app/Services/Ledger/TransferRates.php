<?php

namespace App\Services\Ledger;

use App\Models\AllocationRate;
use App\Models\GlProject;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The SYP rate of each city, with the day it starts: an accountant proposes it, the Financial Manager approves or
 * rejects it, never the person who proposed it. A transfer to a project takes the latest approved rate that has started
 * (decision D9). The flag "allocate at average cost" travels with the rate and stays off unless the proposer sets it.
 */
class TransferRates
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** @return list<string> the cities that have general accounts */
    public function cities(): array
    {
        return array_values(GlProject::query()->where('is_general_account', true)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->all());
    }

    /** @throws TransferRateException */
    public function propose(string $city, string $rate, string $effectiveFrom, bool $averageCost, int $by): AllocationRate
    {
        if (! in_array($city, $this->cities(), true)) {
            throw new TransferRateException(__('ledger.transfers.rate_bad_city'));
        }

        $clean = str_replace([',', ' '], '', trim($rate));

        try {
            $value = $clean === '' ? '0' : Money::normalize($clean, 6);
        } catch (InvalidArgumentException) {
            $value = '0';
        }

        if (bccomp($value, '0', 6) <= 0) {
            throw new TransferRateException(__('ledger.transfers.rate_bad_rate'));
        }

        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveFrom) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $effectiveFrom) : false;
        if (! $day instanceof CarbonImmutable || $day->format('Y-m-d') !== $effectiveFrom) {
            throw new TransferRateException(__('ledger.transfers.rate_bad_date'));
        }

        return AllocationRate::query()->create([
            'city' => $city,
            'rate' => $value,
            'effective_from' => $day->toDateString(),
            'status' => self::PENDING,
            'proposed_by' => $by,
            'allocate_at_average_cost' => $averageCost,
        ]);
    }

    /** @throws TransferRateException */
    public function approve(int $id, int $by): AllocationRate
    {
        return $this->decide($id, $by, self::APPROVED);
    }

    /** @throws TransferRateException */
    public function reject(int $id, int $by): AllocationRate
    {
        return $this->decide($id, $by, self::REJECTED);
    }

    /** The approved rate in force for a city on a day: the latest one that has started, or null when there is none. */
    public function approvedFor(string $city, CarbonImmutable $day): ?AllocationRate
    {
        return AllocationRate::query()
            ->where('city', $city)
            ->where('status', self::APPROVED)
            ->whereDate('effective_from', '<=', $day->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    private function decide(int $id, int $by, string $status): AllocationRate
    {
        $rate = AllocationRate::query()->whereKey($id)->first();

        if ($rate === null || $rate->status !== self::PENDING) {
            throw new TransferRateException(__('ledger.transfers.rate_not_pending'));
        }

        if ($rate->proposed_by === $by) {
            throw new TransferRateException(__('ledger.transfers.rate_own'));
        }

        $rate->update(['status' => $status, 'approved_by' => $by]);

        return $rate;
    }
}
