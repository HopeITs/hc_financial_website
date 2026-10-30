<?php

namespace App\Services\Ledger;

/** The opening voucher OpeningBalances built, or the problems that stop it, with the net assets it writes per part. */
final readonly class BuiltOpening
{
    /**
     * @param  list<string>  $problems
     * @param  array{unrestricted: numeric-string, restricted: numeric-string, unknown: numeric-string}|null  $netAssets
     */
    public function __construct(
        public ?VoucherDraft $draft,
        public array $problems,
        public ?array $netAssets = null,
    ) {}
}
