<?php

namespace App\Services\Chart;

use App\Enums\NormalSide;
use App\Models\GlAccount;
use App\Models\VoucherLine;
use Illuminate\Support\Facades\DB;

/**
 * Balances of the chart in USD from approved vouchers only, shown on each account's normal side;
 * a header carries the sum of every account below it.
 */
class AccountBalances
{
    /** @return array<string, string> account code => balance with two decimals */
    public function all(): array
    {
        $net = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->groupBy('l.account_code')
            ->selectRaw('l.account_code as code, sum(l.debit_usd) as debit, sum(l.credit_usd) as credit')
            ->get();

        $accounts = GlAccount::query()->get(['code', 'parent_code', 'normal_side'])->keyBy('code');

        $rolled = [];
        foreach ($net as $row) {
            $cents = (int) round(((float) $row->debit - (float) $row->credit) * 100);

            for ($step = $row->code; $step !== null && isset($accounts[$step]); $step = $accounts[$step]->parent_code) {
                $rolled[$step] = ($rolled[$step] ?? 0) + $cents;
            }
        }

        $balances = [];
        foreach ($rolled as $code => $cents) {
            $signed = $accounts[$code]->normal_side === NormalSide::Credit ? -$cents : $cents;
            $balances[$code] = number_format($signed / 100, 2, '.', '');
        }

        return $balances;
    }

    /** An account is used once a voucher line (in any status) points at it. */
    public function isUsed(string $code): bool
    {
        return VoucherLine::query()->where('account_code', $code)->exists();
    }
}
