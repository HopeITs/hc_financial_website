<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\PostingRuleViolation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rule 12: a month closes only when the in-transit accounts (11x3, 11x4) and the suspense account (2901) net to
 * zero at the month's end, from approved vouchers, in USD and in every original currency.
 */
class MonthClosesOnlyWhenClearingIsZero
{
    /** The accounts that must be empty: in transit (11x3 USD, 11x4 SYP) and suspense. */
    private const PATTERN = '/^(11\d[34]|2901)$/';

    /**
     * @param  string  $month  YYYY-MM
     */
    public function check(string $month): void
    {
        $end = Carbon::createFromFormat('Y-m-d', $month.'-01')->endOfMonth()->toDateString();

        $rows = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->where('v.date', '<=', $end)
            ->groupBy('l.account_code', 'l.currency')
            ->selectRaw('l.account_code as code, l.currency as currency, sum(l.debit_usd) - sum(l.credit_usd) as usd, sum(l.debit_orig) - sum(l.credit_orig) as orig')
            ->get();

        foreach ($rows as $row) {
            if (preg_match(self::PATTERN, (string) $row->code) !== 1) {
                continue;
            }

            if (bccomp(number_format((float) $row->usd, 2, '.', ''), '0', 2) !== 0 || bccomp(number_format((float) $row->orig, 2, '.', ''), '0', 2) !== 0) {
                throw PostingRuleViolation::of('month_close_blocked', [
                    'month' => $month, 'account' => $row->code, 'balance' => number_format((float) $row->orig, 2, '.', '').' '.$row->currency,
                ]);
            }
        }
    }
}
