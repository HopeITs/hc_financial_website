<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/**
 * A rule that a voucher is checked against before it is posted. Rules report every break they find, so the
 * voucher builder can show the whole check list at once.
 */
interface PostingRule
{
    /** The rule's number in the plan's list of 13. */
    public function number(): int;

    /** @return list<Violation> */
    public function check(VoucherDraft $draft, LedgerContext $context): array;
}
