<?php

namespace App\Data;

/**
 * The cash one project holds, from the ledger's approved vouchers. Money is a two-decimal string, never a float.
 */
final readonly class ProjectCash
{
    /**
     * @param  numeric-string  $syp  the pounds held, as entered
     * @param  numeric-string  $usd  the dollars held
     * @param  numeric-string  $otherUsd  any other currency held (euros), in dollars
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $city,
        public string $syp,
        public string $usd,
        public string $otherUsd = '0.00',
    ) {}

    public function holdsCash(): bool
    {
        return bccomp($this->syp, '0', 2) !== 0 || bccomp($this->usd, '0', 2) !== 0 || bccomp($this->otherUsd, '0', 2) !== 0;
    }
}
