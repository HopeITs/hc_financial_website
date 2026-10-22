<?php

namespace App\Services\Ledger;

/**
 * One broken posting rule: its number (1 to 13), the message in the current language and the line it concerns.
 */
final readonly class Violation
{
    public function __construct(
        public int $rule,
        public string $message,
        public ?int $line = null,
    ) {}
}
