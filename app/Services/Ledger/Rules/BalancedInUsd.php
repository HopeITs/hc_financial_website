<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Services\Ledger\PostingRuleViolation;

/**
 * Rule 4: the voucher balances to the cent in USD. Rounding cents are the builder's job (it adds them as a line
 * on 5503), so here any difference at all is refused. A USD line must also equal its original amount.
 */
class BalancedInUsd implements PostingRule
{
    public function check(VoucherDraft $draft): void
    {
        if (count($draft->lines) < 2) {
            throw PostingRuleViolation::of('too_few_lines');
        }

        foreach ($draft->lines as $index => $line) {
            if ($line->string('currency') === 'USD'
                && (bccomp($line->amount('debit_orig'), $line->amount('debit_usd'), 2) !== 0
                    || bccomp($line->amount('credit_orig'), $line->amount('credit_usd'), 2) !== 0)) {
                throw PostingRuleViolation::of('usd_line_mismatch', ['line' => $index + 1]);
            }
        }

        $debit = $draft->totalDebitUsd();
        $credit = $draft->totalCreditUsd();

        if (bccomp($debit, '0', 2) === 0 || bccomp($debit, $credit, 2) !== 0) {
            throw PostingRuleViolation::of('unbalanced', ['debit' => $debit, 'credit' => $credit]);
        }
    }
}
