<?php

namespace App\Services\Ledger;

use App\Models\MonthClose;
use App\Services\Ledger\Rules\MonthClosesOnlyWhenClearingIsZero;
use Illuminate\Support\Facades\DB;

/**
 * Closes a month once its clearing accounts are zero. Reopening is a separate, audited Head Office action.
 */
class CloseMonth
{
    public function __construct(private readonly MonthClosesOnlyWhenClearingIsZero $clearingIsZero) {}

    /**
     * @param  string  $month  YYYY-MM
     *
     * @throws PostingRuleViolation
     */
    public function handle(string $month, int $userId): MonthClose
    {
        return DB::transaction(function () use ($month, $userId) {
            $this->clearingIsZero->check($month);

            return MonthClose::query()->create(['month' => $month, 'closed_at' => now(), 'closed_by' => $userId]);
        });
    }
}
