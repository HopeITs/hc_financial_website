<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use Illuminate\Support\Facades\DB;

/**
 * The movements of one account as an in and out report: every line of approved vouchers on the account (and on the
 * accounts below it, for a header) in date order, with the counter accounts of the same voucher, the currency and the
 * amounts in the line's own currency and in USD. Debit is "in", credit is "out". A period is optional: without one every
 * movement is listed.
 */
class AccountMovements
{
    /**
     * @return array{rows: list<array{date: string, voucher: string, account: string, description: string|null, counters: list<string>, currency: string, in: string, out: string, in_usd: string, out_usd: string}>, totals: array<string, array{in: string, out: string}>, in_usd: string, out_usd: string}
     */
    public function for(string $accountCode, ?string $from = null, ?string $to = null): array
    {
        $codes = $this->codesUnder($accountCode);

        $lines = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->whereIn('l.account_code', $codes)
            ->when($from !== null && $from !== '', fn ($query) => $query->where('v.date', '>=', $from))
            ->when($to !== null && $to !== '', fn ($query) => $query->where('v.date', '<=', $to))
            ->orderBy('v.date')->orderBy('v.id')->orderBy('l.line_no')
            ->get(['l.id', 'v.id as voucher', 'v.date', 'v.description', 'l.account_code', 'l.currency', 'l.debit_orig', 'l.credit_orig', 'l.debit_usd', 'l.credit_usd']);

        $names = GlAccount::query()->get(['code', 'name_en', 'name_ar'])->keyBy('code');
        $label = fn (string $code): string => $code.' · '.(isset($names[$code]) ? $names[$code]->localizedName() : '');

        $counters = [];
        if ($lines->isNotEmpty()) {
            $counters = DB::table('gl_voucher_lines')
                ->whereIn('voucher_id', $lines->pluck('voucher')->unique()->all())
                ->whereNotIn('account_code', $codes)
                ->orderBy('line_no')
                ->get(['voucher_id', 'account_code'])
                ->groupBy('voucher_id')
                ->map(fn ($group) => $group->pluck('account_code')->unique()->map($label)->values()->all())
                ->all();
        }

        $rows = [];
        $totals = [];
        $inUsd = '0.00';
        $outUsd = '0.00';
        foreach ($lines as $line) {
            $in = $this->money($line->debit_orig);
            $out = $this->money($line->credit_orig);
            $usdIn = $this->money($line->debit_usd);
            $usdOut = $this->money($line->credit_usd);

            $totals[$line->currency] ??= ['in' => '0.00', 'out' => '0.00'];
            $totals[$line->currency]['in'] = bcadd($totals[$line->currency]['in'], $in, 2);
            $totals[$line->currency]['out'] = bcadd($totals[$line->currency]['out'], $out, 2);
            $inUsd = bcadd($inUsd, $usdIn, 2);
            $outUsd = bcadd($outUsd, $usdOut, 2);

            $rows[] = [
                'date' => substr((string) $line->date, 0, 10),
                'voucher' => (string) $line->voucher,
                'account' => (string) $line->account_code,
                'description' => $line->description,
                'counters' => $counters[$line->voucher] ?? [],
                'currency' => (string) $line->currency,
                'in' => $in,
                'out' => $out,
                'in_usd' => $usdIn,
                'out_usd' => $usdOut,
            ];
        }

        ksort($totals);

        return ['rows' => $rows, 'totals' => $totals, 'in_usd' => $inUsd, 'out_usd' => $outUsd];
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
