<?php

namespace App\Services\Chart;

use App\Enums\NormalSide;
use App\Models\GlAccount;
use App\Models\VoucherLine;
use App\Services\Ledger\Currencies;
use App\Support\Money;
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

    /**
     * Balances in an account's own currency (finance request of 2026-11-21): for each account kept in a currency other
     * than the dollar, the amount actually held in that currency, its dollar value in the books, and the average rate
     * between them. A header shows them too when everything below it is in that one currency (the pound safes of a
     * city, for example). Approved vouchers only, on the account's normal side, like the dollar balances.
     *
     * @return array<string, array{currency: string, amount: string, usd: string, rate: string|null}>
     */
    public function inOwnCurrency(): array
    {
        $accounts = GlAccount::query()->get(['code', 'parent_code', 'normal_side', 'currency'])->keyBy('code');
        $foreign = $accounts->filter(fn (GlAccount $account): bool => ! in_array((string) $account->currency, ['', 'Any', Currencies::BASE], true));

        if ($foreign->isEmpty()) {
            return [];
        }

        $net = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->whereIn('l.account_code', $foreign->keys()->all())
            ->groupBy('l.account_code')
            ->selectRaw('l.account_code as code, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        /** @var array<string, array<string, array{orig: string, usd: string}>> $rolled code => currency => sums */
        $rolled = [];
        foreach ($net as $row) {
            $currency = (string) $foreign[$row->code]->currency;
            $orig = Money::normalize((string) $row->orig);
            $usd = Money::normalize((string) $row->usd);

            for ($step = $row->code; $step !== null && isset($accounts[$step]); $step = $accounts[$step]->parent_code) {
                $sums = $rolled[$step][$currency] ?? ['orig' => '0.00', 'usd' => '0.00'];
                $rolled[$step][$currency] = ['orig' => bcadd($sums['orig'], $orig, 2), 'usd' => bcadd($sums['usd'], $usd, 2)];
            }
        }

        $dollars = $this->all();
        $currencies = app(Currencies::class);
        $balances = [];

        foreach ($rolled as $code => $byCurrency) {
            if (count($byCurrency) !== 1) {
                continue;
            }

            $currency = (string) array_key_first($byCurrency);
            $sums = $byCurrency[$currency];
            $credit = $accounts[$code]->normal_side === NormalSide::Credit;
            $orig = $credit ? bcmul($sums['orig'], '-1', 2) : $sums['orig'];
            $usd = $credit ? bcmul($sums['usd'], '-1', 2) : $sums['usd'];

            // A header shows the currency only when nothing else is under it: its dollars are all this currency's.
            if (! $foreign->has($code) && bccomp($usd, $dollars[$code] ?? '0.00', 2) !== 0) {
                continue;
            }

            $negative = bccomp($orig, '0', 2) < 0 && bccomp($usd, '0', 2) < 0;
            $balances[$code] = [
                'currency' => $currency,
                'amount' => $orig,
                'usd' => $usd,
                'rate' => $negative
                    ? $currencies->rateOf($currency, bcmul($orig, '-1', 2), bcmul($usd, '-1', 2))
                    : $currencies->rateOf($currency, $orig, $usd),
            ];
        }

        return $balances;
    }

    /** An account is used once a voucher line (in any status) points at it. */
    public function isUsed(string $code): bool
    {
        return VoucherLine::query()->where('account_code', $code)->exists();
    }
}
