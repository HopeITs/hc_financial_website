<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Models\Voucher;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 13: a voucher loaded from the Windows program carries its legacy `action`.`ID`, once; a web voucher has none. */
class MigratedVouchersKeepTheirLegacyId implements PostingRule
{
    public function check(VoucherDraft $draft): void
    {
        if ($draft->source === 'migration' && $draft->legacyActionId === null) {
            throw PostingRuleViolation::of('legacy_id_missing');
        }

        if ($draft->source !== 'migration' && $draft->legacyActionId !== null) {
            throw PostingRuleViolation::of('legacy_id_on_web_voucher');
        }

        if ($draft->legacyActionId !== null && Voucher::withTrashed()->where('legacy_action_id', $draft->legacyActionId)->exists()) {
            throw PostingRuleViolation::of('legacy_id_taken', ['id' => $draft->legacyActionId]);
        }
    }
}
