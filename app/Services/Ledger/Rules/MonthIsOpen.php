<?php

namespace App\Services\Ledger\Rules;

use App\Models\MonthClose;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/** Rule 8: nothing is posted into, or changed in, a month that has been closed. */
final class MonthIsOpen implements PostingRule
{
    public function number(): int
    {
        return 8;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        return $this->checkMonth($draft->month());
    }

    /**
     * @param  string  $month  YYYY-MM
     * @return list<Violation>
     */
    public function checkMonth(string $month): array
    {
        if (MonthClose::query()->whereKey($month)->exists()) {
            return [new Violation($this->number(), __('ledger.rules.month_closed', ['month' => $month]))];
        }

        return [];
    }
}
