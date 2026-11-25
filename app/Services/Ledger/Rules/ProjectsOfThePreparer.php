<?php

namespace App\Services\Ledger\Rules;

use App\Enums\VoucherSource;
use App\Models\User;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;
use App\Services\Projects\ProjectAccess;

/**
 * Finance request of 2026-11-30: a person limited to their projects (a project manager, or anyone switched on for it on
 * the users page) enters vouchers on those projects only, whatever screen the voucher comes from. A line with no
 * project is not held to it; migrated vouchers are history.
 */
final class ProjectsOfThePreparer implements PostingRule
{
    public function number(): int
    {
        return 17;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        if ($draft->source === VoucherSource::Migration) {
            return [];
        }

        $preparer = User::query()->find($draft->preparedBy);
        $limit = $preparer instanceof User ? app(ProjectAccess::class)->limitedTo($preparer) : null;

        if ($limit === null) {
            return [];
        }

        $outside = [];
        foreach ($draft->lines as $line) {
            if ($line->projectCode !== null && $line->projectCode !== '' && ! in_array($line->projectCode, $limit, true)) {
                $outside[$line->projectCode] = true;
            }
        }

        return $outside === []
            ? []
            : [new Violation($this->number(), __('ledger.rules.project_not_yours', ['projects' => implode('، ', array_keys($outside))]))];
    }
}
