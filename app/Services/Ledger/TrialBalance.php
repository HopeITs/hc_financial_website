<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use Illuminate\Support\Facades\DB;

/**
 * The trial balance as at a date, from approved vouchers only: per posting account the debit, credit and net,
 * grouped by class. The Dr = Cr check always runs on the USD amounts, which is the ledger's own basis.
 *
 * In the SYP view a USD amount is multiplied by the typed rate and a SYP line stays as typed on the voucher
 * (decision of round 2: SYP stays SYP, only USD is converted). Net is debit minus credit.
 */
class TrialBalance
{
    /**
     * @return array{classes: list<array{class: string, rows: list<array{code: string, name_en: string, name_ar: string, debit: string, credit: string, net: string}>, debit: string, credit: string, net: string}>, debit: string, credit: string, net: string, balanced: bool, difference: string}
     */
    public function asAt(?string $asAt = null, string $currency = 'USD', ?string $rate = null): array
    {
        $syp = $currency === 'SYP';
        $rate = $syp ? $this->rate($rate) : '1';

        $sums = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->when($asAt !== null && $asAt !== '', fn ($query) => $query->where('v.date', '<=', $asAt))
            ->groupBy('l.account_code')
            ->selectRaw(
                'l.account_code as code, '
                .'sum(l.debit_usd) as debit_usd, sum(l.credit_usd) as credit_usd, '
                ."sum(case when l.currency = 'SYP' then l.debit_orig else 0 end) as debit_syp, "
                ."sum(case when l.currency = 'SYP' then l.credit_orig else 0 end) as credit_syp, "
                ."sum(case when l.currency = 'SYP' then 0 else l.debit_usd end) as debit_other, "
                ."sum(case when l.currency = 'SYP' then 0 else l.credit_usd end) as credit_other"
            )
            ->get()->keyBy('code');

        $totalDebit = '0.00';
        $totalCredit = '0.00';
        $classes = [];

        $accounts = GlAccount::query()->orderBy('code')->get()->filter(fn (GlAccount $account) => $sums->has($account->code));

        foreach ($accounts->groupBy(fn (GlAccount $account) => $account->class->value) as $class => $group) {
            $rows = [];
            $debit = '0.00';
            $credit = '0.00';

            foreach ($group as $account) {
                $sum = $sums[$account->code];

                $totalDebit = bcadd($totalDebit, $this->money($sum->debit_usd), 2);
                $totalCredit = bcadd($totalCredit, $this->money($sum->credit_usd), 2);

                $dr = $syp ? bcadd($this->money($sum->debit_syp), bcmul($this->money($sum->debit_other), $rate, 2), 2) : $this->money($sum->debit_usd);
                $cr = $syp ? bcadd($this->money($sum->credit_syp), bcmul($this->money($sum->credit_other), $rate, 2), 2) : $this->money($sum->credit_usd);

                $debit = bcadd($debit, $dr, 2);
                $credit = bcadd($credit, $cr, 2);
                $rows[] = [
                    'code' => $account->code,
                    'name_en' => $account->name_en,
                    'name_ar' => $account->name_ar,
                    'debit' => $dr,
                    'credit' => $cr,
                    'net' => bcsub($dr, $cr, 2),
                ];
            }

            $classes[] = ['class' => (string) $class, 'rows' => $rows, 'debit' => $debit, 'credit' => $credit, 'net' => bcsub($debit, $credit, 2)];
        }

        $difference = bcsub($totalDebit, $totalCredit, 2);
        $debit = array_reduce($classes, fn (string $sum, array $class) => bcadd($sum, $class['debit'], 2), '0.00');
        $credit = array_reduce($classes, fn (string $sum, array $class) => bcadd($sum, $class['credit'], 2), '0.00');

        return ['classes' => $classes, 'debit' => $debit, 'credit' => $credit, 'net' => bcsub($debit, $credit, 2), 'balanced' => bccomp($difference, '0', 2) === 0, 'difference' => $difference];
    }

    /** @return numeric-string */
    private function rate(?string $rate): string
    {
        return is_numeric($rate) && bccomp((string) $rate, '0', 6) > 0 ? number_format((float) $rate, 6, '.', '') : '1.000000';
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
