<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/**
 * Rule 3: a line is a debit or a credit with a positive amount, never both, never negative, in the original,
 * USD and donor-currency columns alike.
 */
final class DebitOrCreditNeverBoth implements PostingRule
{
    public function number(): int
    {
        return 3;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        $violations = [];

        foreach ($draft->lines as $index => $line) {
            if (! $this->isValid($line)) {
                $violations[] = new Violation($this->number(), __('ledger.rules.debit_or_credit'), $index + 1);
            }
        }

        return $violations;
    }

    private function isValid(LineDraft $line): bool
    {
        $pairs = [
            [$line->debitOrig, $line->creditOrig],
            [$line->debitUsd, $line->creditUsd],
            [$line->debitDonor, $line->creditDonor],
        ];

        foreach ($pairs as [$debit, $credit]) {
            if (bccomp($debit, '0', 2) < 0 || bccomp($credit, '0', 2) < 0) {
                return false;
            }

            if (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0) {
                return false;
            }
        }

        $hasDebit = bccomp($line->debitOrig, '0', 2) > 0;
        $hasCredit = bccomp($line->creditOrig, '0', 2) > 0;

        return $hasDebit !== $hasCredit;
    }
}
