<?php

namespace App\Services\Ledger;

/** The filters the Windows program offers on its operational reports. Empty means "all". */
final readonly class ReportFilters
{
    public function __construct(
        public ?string $project = null,
        public ?string $donor = null,
        public ?string $account = null,
        public ?string $currency = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    /** @param  array<string, string|null>  $values */
    public static function from(array $values): self
    {
        $pick = fn (string $key): ?string => isset($values[$key]) && $values[$key] !== '' ? $values[$key] : null;

        return new self($pick('project'), $pick('donor'), $pick('account'), $pick('currency'), $pick('from'), $pick('to'));
    }
}
