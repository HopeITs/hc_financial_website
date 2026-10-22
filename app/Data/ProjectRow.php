<?php

namespace App\Data;

/**
 * One project as the projects list and the project page show it (the balances are added separately).
 */
final readonly class ProjectRow
{
    public function __construct(
        public int $accountId,
        public string $name,
        public ?int $cityId,
        public ?string $cityName,
        public ?int $subProjectId,
        public ?string $subProjectName,
        public ?string $programName,
        public string $donors,
        public bool $closed,
        public ?string $beginDate,
        public ?string $endDate,
    ) {}
}
