<?php

namespace App\Data;

/**
 * The balance of one cash account in the treasury position: in its own currency and in USD. Money is a two-decimal string.
 */
final readonly class TreasuryCash
{
    /**
     * @param  numeric-string  $orig
     * @param  numeric-string  $usd
     */
    public function __construct(
        public string $code,
        public string $nameEn,
        public string $nameAr,
        public string $currency,
        public string $orig,
        public string $usd,
    ) {}

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->nameAr : $this->nameEn;
    }
}
