<?php

namespace App\Services\Ledger;

use Illuminate\Support\Facades\DB;

/**
 * Net assets as the Financial Manager asked for them (request 03): computed, never typed. From approved vouchers in USD:
 *
 *  - total = assets − liabilities.
 *  - with donor restrictions (3200) = what the lines of restricted projects earned and spent (income − expenses), plus
 *    anything on the net assets accounts for those projects: the same measure the project balances use for restricted
 *    money, so a dollar moved from the general account to a project does not change the split.
 *  - without donor restrictions (3100) = the rest.
 *
 * Because every voucher balances, the total also equals income − expenses plus the net assets accounts, so the statement of
 * financial position and this figure always agree.
 */
class NetAssets
{
    public const RESTRICTED = 'With donor restrictions';

    /** The chart headers that show a computed figure: the total, then without and with donor restrictions. */
    public const CHART_CODES = ['3000', '3100', '3200'];

    /** @return array{total: numeric-string, restricted: numeric-string, unrestricted: numeric-string} */
    public function asAt(?string $date = null): array
    {
        $sums = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->when($date !== null && $date !== '', fn ($query) => $query->where('v.date', '<=', $date))
            ->selectRaw(
                "sum(case when a.class in ('assets', 'liabilities') then l.debit_usd - l.credit_usd else 0 end) as total, "
                ."sum(case when a.class in ('income', 'expenses', 'net_assets') and l.restriction = ? then l.credit_usd - l.debit_usd else 0 end) as restricted",
                [self::RESTRICTED]
            )
            ->first();

        $total = $this->money($sums->total ?? 0);
        $restricted = $this->money($sums->restricted ?? 0);

        return ['total' => $total, 'restricted' => $restricted, 'unrestricted' => bcsub($total, $restricted, 2)];
    }

    /**
     * The chart's balances with the net assets headers replaced by the computed figures: 3000 the total, 3100 without and
     * 3200 with donor restrictions. Posted balances of the accounts below them stay as they are.
     *
     * @param  array<array-key, string>  $balances  account code => balance on its normal side (PHP keeps a numeric code as an int key)
     * @return array<array-key, string>
     */
    public function onChart(array $balances): array
    {
        $net = $this->asAt();

        return array_replace($balances, array_combine(self::CHART_CODES, [$net['total'], $net['unrestricted'], $net['restricted']]));
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
