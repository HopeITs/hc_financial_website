<?php

namespace App\Data;

/**
 * The balance of one project account. Money is a two-decimal string, never a float.
 */
final readonly class ProjectBalance
{
    /**
     * @param  numeric-string  $syp
     * @param  numeric-string  $sypNew
     * @param  numeric-string  $usd
     */
    public function __construct(
        public int $accountId,
        public string $name,
        public ?int $cityId,
        public ?string $cityName,
        public ?string $subProjectName,
        public string $syp,
        public string $sypNew,
        public string $usd,
    ) {}
}
