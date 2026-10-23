<?php

namespace App\Services\Ledger;

/**
 * What the voucher builder made of a form: the draft to check and post (null while the header is not usable), the
 * values each line ended up with once the rules of the form were applied (account from the budget line, currency from the
 * account, the rate, the USD amount), and the problems that stop it being turned into a draft at all.
 */
final readonly class BuiltVoucher
{
    /**
     * @param  array<int, array{account: string, currency: string, project: string, rate: string, usd: string}>  $rows  by form row
     * @param  list<string>  $problems
     */
    public function __construct(
        public ?VoucherDraft $draft,
        public array $rows,
        public array $problems,
    ) {}
}
