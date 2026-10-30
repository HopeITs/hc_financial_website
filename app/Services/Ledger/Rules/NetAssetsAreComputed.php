<?php

namespace App\Services\Ledger\Rules;

use App\Enums\AccountClass;
use App\Enums\VoucherSource;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/**
 * Rule 16 (F-05): net assets are computed as assets − liabilities, so no one types a line on a 3xxx account in the entry
 * screens. Vouchers migrated from the Windows program keep what they had; opening balances and the year-end close will
 * write these accounts through their own vouchers.
 */
final class NetAssetsAreComputed implements PostingRule
{
    public function number(): int
    {
        return 16;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        if ($draft->source !== VoucherSource::Web) {
            return [];
        }

        $violations = [];

        foreach ($draft->lines as $index => $line) {
            if ($context->account($line->accountCode)?->class === AccountClass::NetAssets) {
                $violations[] = new Violation($this->number(), __('ledger.rules.net_assets_computed', ['account' => $line->accountCode]), $index + 1);
            }
        }

        return $violations;
    }
}
