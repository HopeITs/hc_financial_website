<?php

namespace App\Services\Ledger\Rules;

use App\Enums\VoucherSource;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/**
 * Rule 13: a voucher migrated from the Windows program keeps the id of its operation, once; a voucher made on
 * the web carries none. This is what makes a repeated import safe.
 */
final class MigratedVoucherKeepsLegacyId implements PostingRule
{
    public function number(): int
    {
        return 13;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        if ($draft->source === VoucherSource::Web) {
            return $draft->legacyActionId === null
                ? []
                : [new Violation($this->number(), __('ledger.rules.legacy_id_on_web'))];
        }

        if ($draft->legacyActionId === null || $draft->legacyActionId <= 0) {
            return [new Violation($this->number(), __('ledger.rules.legacy_id_missing'))];
        }

        if ($context->legacyIdExists($draft->legacyActionId)) {
            return [new Violation($this->number(), __('ledger.rules.legacy_id_duplicate', ['id' => $draft->legacyActionId]))];
        }

        return [];
    }
}
