<?php

namespace App\Services\Ledger;

use App\Enums\VoucherStatus;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The average rate of the Syrian pounds a project holds: the pounds in its pound cash accounts divided by the dollars
 * they were bought for (approved vouchers only). A payment in pounds is booked at this rate, so the dollars leaving
 * the project match the dollars that went in.
 */
class ProjectAverageRate
{
    /**
     * Pounds per dollar, or null when the project holds no pounds (or has nothing in dollars to measure against).
     *
     * @return numeric-string|null
     */
    public function rate(string $projectCode): ?string
    {
        $row = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at')
            ->where('a.is_cash', true)
            ->where('a.currency', 'SYP')
            ->where('l.project_code', $projectCode)
            ->selectRaw('sum(l.debit_orig) - sum(l.credit_orig) as pounds, sum(l.debit_usd) - sum(l.credit_usd) as dollars')
            ->first();

        $pounds = Money::normalize($row->pounds ?? null);
        $dollars = Money::normalize($row->dollars ?? null);

        if (bccomp($pounds, '0', 2) <= 0 || bccomp($dollars, '0.01', 2) < 0) {
            return null;
        }

        return bcdiv($pounds, $dollars, 6);
    }
}
