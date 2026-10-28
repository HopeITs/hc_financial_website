<?php

namespace App\Services\Ledger;

use App\Enums\VoucherStatus;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The average rate of a foreign currency a project holds (pounds by default): what its cash accounts of that currency hold
 * divided into the dollars they were bought for (approved vouchers only). A payment in that currency is booked at this
 * rate, so the dollars leaving the project match the dollars that went in. The rate is in the currency's own quoting.
 */
class ProjectAverageRate
{
    public function __construct(private readonly Currencies $currencies) {}

    /**
     * The currency's rate (pounds per dollar, dollars per euro, ...), or null when the project holds none of it (or has
     * nothing in dollars to measure against).
     *
     * @return numeric-string|null
     */
    public function rate(string $projectCode, string $currency = 'SYP'): ?string
    {
        $held = $this->holding($projectCode, $currency);

        return $this->currencies->rateOf($currency, $held['orig'], $held['usd']);
    }

    /**
     * What a project holds in the cash accounts of one currency: the amount in that currency and the dollars it stands
     * for in the books (approved vouchers only).
     *
     * @return array{orig: numeric-string, usd: numeric-string}
     */
    public function holding(string $projectCode, string $currency): array
    {
        $row = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at')
            ->where('a.is_cash', true)
            ->where('a.currency', $currency)
            ->where('l.project_code', $projectCode)
            ->selectRaw('sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->first();

        return ['orig' => Money::normalize($row->orig ?? null), 'usd' => Money::normalize($row->usd ?? null)];
    }
}
