<?php

namespace App\Services\Ledger;

/**
 * What the transfer builder made of a form: the draft to post (null while there are problems), the problems in plain
 * words, and the figures the screen previews (the rate used, the USD value moved, and the difference that goes to 5503).
 */
final readonly class BuiltTransfer
{
    /**
     * @param  list<string>  $problems
     * @param  numeric-string|null  $rate
     * @param  numeric-string|null  $usd
     * @param  numeric-string|null  $difference  positive is a gain for the general account
     */
    public function __construct(
        public ?VoucherDraft $draft,
        public array $problems,
        public ?string $rate = null,
        public ?string $usd = null,
        public ?string $difference = null,
    ) {}
}
