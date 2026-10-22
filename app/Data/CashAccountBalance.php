<?php

namespace App\Data;

/**
 * What one cash account holds for a project. Money is a two-decimal string, never a float.
 */
final readonly class CashAccountBalance
{
    /**
     * @param  numeric-string  $syp
     * @param  numeric-string  $sypNew
     * @param  numeric-string  $usd
     */
    public function __construct(
        public int $cashAccountId,
        public string $name,
        public string $syp,
        public string $sypNew,
        public string $usd,
    ) {}
}
