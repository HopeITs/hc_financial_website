<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/** Rule 5: every tag the account requires (project, budget line, fund source, contact, center) is filled on the line. */
final class RequiredTagsFilled implements PostingRule
{
    public function number(): int
    {
        return 5;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        $violations = [];

        foreach ($draft->lines as $index => $line) {
            $account = $context->account($line->accountCode);

            if ($account === null) {
                continue;
            }

            $missing = array_values(array_filter($account->required_tags ?? [], fn (string $tag) => $line->tag($tag) === null));

            if ($missing !== []) {
                $violations[] = new Violation($this->number(), __('ledger.rules.tags_missing', [
                    'account' => $account->code, 'tags' => implode(', ', $missing),
                ]), $index + 1);
            }
        }

        return $violations;
    }
}
