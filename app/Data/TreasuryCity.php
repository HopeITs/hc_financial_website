<?php

namespace App\Data;

/**
 * One city of the treasury position: its two general accounts, the pounds bought in bulk, the cash in its safes and in
 * transit, and the exchange reserve. Pounds held and bought are in SYP, everything else in USD unless stated.
 */
final readonly class TreasuryCity
{
    /**
     * @param  numeric-string  $usdHeld  the dollars the general USD account holds
     * @param  numeric-string  $sypHeld  the pounds the general SYP account holds
     * @param  numeric-string  $sypBookUsd  what those pounds were bought for, in USD
     * @param  numeric-string|null  $sypAverageRate  pounds per dollar of what is held, or null when nothing is held
     * @param  numeric-string  $purchasedSyp  pounds bought in bulk (transfers into the pound safe)
     * @param  numeric-string  $purchasedUsd  dollars paid for them
     * @param  numeric-string|null  $purchaseRate  pounds per dollar paid
     * @param  list<TreasuryCash>  $safes
     * @param  list<TreasuryCash>  $inTransit
     * @param  numeric-string  $reserve  exchange differences kept on the general accounts (credit minus debit)
     */
    public function __construct(
        public string $city,
        public ?string $usdProject,
        public ?string $sypProject,
        public string $usdHeld,
        public string $sypHeld,
        public string $sypBookUsd,
        public ?string $sypAverageRate,
        public string $purchasedSyp,
        public string $purchasedUsd,
        public ?string $purchaseRate,
        public int $purchaseCount,
        public ?string $lastPurchase,
        public array $safes,
        public array $inTransit,
        public string $reserve,
    ) {}

    /** The city name in the current language, or as stored when there is no translation. */
    public function label(): string
    {
        $key = 'ledger.treasury.cities.'.$this->city;
        $text = __($key);

        return $text === $key ? $this->city : $text;
    }
}
