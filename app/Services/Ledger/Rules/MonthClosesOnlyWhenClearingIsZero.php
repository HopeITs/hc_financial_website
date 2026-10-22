<?php

namespace App\Services\Ledger\Rules;

use App\Enums\VoucherStatus;
use App\Models\GlAccount;
use App\Models\VoucherLine;
use App\Services\Ledger\Violation;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Rule 12: a month can only be closed when the in-transit accounts (11x3, 11x4) and the suspense account (2901)
 * hold nothing, in both their own currency and USD, counting approved vouchers up to the end of that month.
 */
final class MonthClosesOnlyWhenClearingIsZero
{
    public function number(): int
    {
        return 12;
    }

    /**
     * @param  string  $month  YYYY-MM
     * @return list<Violation>
     */
    public function check(string $month): array
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', $month)->endOfMonth()->toDateString();

        $codes = GlAccount::query()->pluck('code')
            ->filter(fn (string $code) => preg_match((string) config('ledger.in_transit_pattern'), $code) === 1
                || in_array($code, (array) config('ledger.clearing_accounts'), true))
            ->values()->all();

        $balances = VoucherLine::query()
            ->join('gl_vouchers', 'gl_vouchers.id', '=', 'gl_voucher_lines.voucher_id')
            ->where('gl_vouchers.status', VoucherStatus::Approved->value)
            ->whereNull('gl_vouchers.deleted_at')
            ->where('gl_vouchers.date', '<=', $end)
            ->whereIn('gl_voucher_lines.account_code', $codes)
            ->groupBy('gl_voucher_lines.account_code')
            ->selectRaw('gl_voucher_lines.account_code as code, sum(debit_orig) - sum(credit_orig) as orig, sum(debit_usd) - sum(credit_usd) as usd')
            ->orderBy('gl_voucher_lines.account_code')
            ->toBase()
            ->get();

        $violations = [];

        foreach ($balances as $row) {
            $orig = Money::normalize($row->orig);
            $usd = Money::normalize($row->usd);

            if (bccomp($orig, '0', 2) !== 0 || bccomp($usd, '0', 2) !== 0) {
                $violations[] = new Violation($this->number(), __('ledger.rules.month_not_clear', [
                    'account' => $row->code, 'month' => $month, 'balance' => $orig.' / '.$usd.' USD',
                ]));
            }
        }

        return $violations;
    }
}
