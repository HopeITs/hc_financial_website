<?php

namespace App\Data;

use App\Support\Money;

/**
 * The treasury position as at a day (or now): every city, and the cash that belongs to no city (banks, the transfer
 * company), with the totals the screen shows at the top.
 */
final readonly class TreasuryReport
{
    /**
     * @param  list<TreasuryCity>  $cities
     * @param  list<TreasuryCash>  $other  cash accounts with no city: the transfer company and the banks
     */
    public function __construct(
        public ?string $asAt,
        public array $cities,
        public array $other,
    ) {}

    /** @return numeric-string */
    public function totalUsdHeld(): string
    {
        return $this->sum(fn (TreasuryCity $city) => $city->usdHeld);
    }

    /** @return numeric-string */
    public function totalSypHeld(): string
    {
        return $this->sum(fn (TreasuryCity $city) => $city->sypHeld);
    }

    /** @return numeric-string */
    public function totalInTransitUsd(): string
    {
        $total = Money::zero();

        foreach ($this->cities as $city) {
            foreach ($city->inTransit as $row) {
                $total = bcadd($total, $row->usd, 2);
            }
        }

        return $total;
    }

    /** @return numeric-string */
    public function totalReserve(): string
    {
        return $this->sum(fn (TreasuryCity $city) => $city->reserve);
    }

    /** What the transfer company (account 1161) holds. */
    public function transferCompanyUsd(): string
    {
        foreach ($this->other as $row) {
            if ($row->code === '1161') {
                return $row->usd;
            }
        }

        return Money::zero();
    }

    /**
     * @param  callable(TreasuryCity): numeric-string  $amount
     * @return numeric-string
     */
    private function sum(callable $amount): string
    {
        $total = Money::zero();

        foreach ($this->cities as $city) {
            $total = bcadd($total, $amount($city), 2);
        }

        return $total;
    }
}
