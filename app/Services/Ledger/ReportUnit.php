<?php

namespace App\Services\Ledger;

/** The currency an operational report is shown in: USD, or SYP with the typed rate for converting dollar lines. */
final readonly class ReportUnit
{
    /** @param  numeric-string  $rate */
    public function __construct(public bool $syp, public string $rate) {}
}
