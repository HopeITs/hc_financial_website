<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 3: a line is a debit or a credit, never both and never negative, in every currency column pair. */
class DebitOrCreditNeverBoth implements PostingRule
{
    private const PAIRS = [['debit_orig', 'credit_orig'], ['debit_usd', 'credit_usd'], ['debit_donor', 'credit_donor']];

    public function check(VoucherDraft $draft): void
    {
        foreach ($draft->lines as $index => $line) {
            foreach (self::PAIRS as [$debit, $credit]) {
                $dr = $line->amount($debit);
                $cr = $line->amount($credit);

                if (bccomp($dr, '0', 2) < 0 || bccomp($cr, '0', 2) < 0) {
                    throw PostingRuleViolation::of('negative_amount', ['line' => $index + 1]);
                }

                if (bccomp($dr, '0', 2) > 0 && bccomp($cr, '0', 2) > 0) {
                    throw PostingRuleViolation::of('debit_and_credit', ['line' => $index + 1]);
                }
            }
        }
    }
}
