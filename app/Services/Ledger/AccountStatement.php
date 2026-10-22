<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use Illuminate\Support\Facades\DB;

/**
 * The statement of one account in USD, from approved vouchers only: an opening balance (everything before the first
 * day), the lines of the period in date order with a running balance, and the closing balance. A header account
 * carries every posting account below it. Balances are signed debit minus credit; the screen prints them as Dr or Cr.
 */
class AccountStatement
{
    /**
     * @return array{opening: string, rows: list<array{date: string, voucher: string, type: string, description: string|null, project: string|null, debit: string, credit: string, balance: string}>, debit: string, credit: string, closing: string}
     */
    public function for(string $accountCode, ?string $project = null, ?string $from = null, ?string $to = null): array
    {
        $codes = $this->codesUnder($accountCode);

        $base = fn () => DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->whereIn('l.account_code', $codes)
            ->when($project !== null && $project !== '', fn ($query) => $query->where('l.project_code', $project));

        $opening = '0.00';
        if ($from !== null && $from !== '') {
            $before = $base()->where('v.date', '<', $from)->selectRaw('coalesce(sum(l.debit_usd), 0) as debit, coalesce(sum(l.credit_usd), 0) as credit')->first();
            $opening = bcsub($this->money($before->debit ?? 0), $this->money($before->credit ?? 0), 2);
        }

        $lines = $base()
            ->when($from !== null && $from !== '', fn ($query) => $query->where('v.date', '>=', $from))
            ->when($to !== null && $to !== '', fn ($query) => $query->where('v.date', '<=', $to))
            ->orderBy('v.date')->orderBy('v.id')->orderBy('l.line_no')
            ->get(['v.id as voucher', 'v.type', 'v.date', 'v.description', 'l.project_code', 'l.debit_usd', 'l.credit_usd']);

        $balance = $opening;
        $debit = '0.00';
        $credit = '0.00';
        $rows = [];
        foreach ($lines as $line) {
            $dr = $this->money($line->debit_usd);
            $cr = $this->money($line->credit_usd);
            $balance = bcsub(bcadd($balance, $dr, 2), $cr, 2);
            $debit = bcadd($debit, $dr, 2);
            $credit = bcadd($credit, $cr, 2);

            $rows[] = [
                'date' => substr((string) $line->date, 0, 10),
                'voucher' => (string) $line->voucher,
                'type' => (string) $line->type,
                'description' => $line->description,
                'project' => $line->project_code,
                'debit' => $dr,
                'credit' => $cr,
                'balance' => $balance,
            ];
        }

        return ['opening' => $opening, 'rows' => $rows, 'debit' => $debit, 'credit' => $credit, 'closing' => $balance];
    }

    /**
     * The account itself and every account below it.
     *
     * @return list<string>
     */
    private function codesUnder(string $code): array
    {
        $children = GlAccount::query()->get(['code', 'parent_code'])->groupBy('parent_code');
        $found = [$code];
        for ($i = 0; $i < count($found); $i++) {
            foreach ($children->get($found[$i], []) as $child) {
                $found[] = $child->code;
            }
        }

        return $found;
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
