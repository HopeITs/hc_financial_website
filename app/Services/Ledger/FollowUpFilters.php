<?php

namespace App\Services\Ledger;

/**
 * The one filter bar of the follow-up reports: a date range, a city and a project. Empty means "all".
 * `limitedTo` holds the project codes a project manager may see; null means not limited.
 */
final readonly class FollowUpFilters
{
    /** @param  list<string>|null  $limitedTo */
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?string $city = null,
        public ?string $project = null,
        public ?array $limitedTo = null,
    ) {}

    /**
     * @param  array<string, string|null>  $values
     * @param  list<string>|null  $limitedTo
     */
    public static function from(array $values, ?array $limitedTo = null): self
    {
        $pick = fn (string $key): ?string => isset($values[$key]) && $values[$key] !== '' ? $values[$key] : null;

        return new self($pick('from'), $pick('to'), $pick('city'), $pick('project'), $limitedTo);
    }
}
