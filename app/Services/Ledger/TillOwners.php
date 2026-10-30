<?php

namespace App\Services\Ledger;

use App\Models\GlAccount;
use App\Models\GlProject;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Each cash account (till) split by its owners, from approved ledger lines: the money of a till belongs to the project or
 * general account tagged on each line. A till's balance is the sum of all its approved lines; the owners' rows are the
 * sums of the lines that carry a project, so the rows plus `unowned` (lines with no project) are the balance exactly.
 * Everything is added with bcmath on the stored two-decimal sums.
 */
class TillOwners
{
    /**
     * @param  list<string>|null  $projectCodes  only these owners' rows, or null for all (a person limited to some projects)
     * @return list<array{code: string, name_en: string, name_ar: string, city: string|null, currency: string, balance: numeric-string, balance_usd: numeric-string, owners: list<array{project_code: string, project_name: string, general: bool, amount: numeric-string, usd: numeric-string}>, unowned: numeric-string, unowned_usd: numeric-string}>
     */
    public function tills(?array $projectCodes = null): array
    {
        $sums = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->where('a.is_cash', true)
            ->groupBy('l.account_code', 'l.project_code')
            ->selectRaw('l.account_code, l.project_code, sum(l.debit_orig) - sum(l.credit_orig) as amount, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        $projects = GlProject::query()->get(['code', 'name', 'is_general_account'])->keyBy('code');
        $accounts = GlAccount::query()->whereIn('code', $sums->pluck('account_code')->unique()->all())->orderBy('code')->get()->keyBy('code');

        $tills = [];
        foreach ($sums->groupBy('account_code') as $code => $group) {
            $account = $accounts->get($code);
            if ($account === null) {
                continue;
            }

            $balance = Money::zero();
            $balanceUsd = Money::zero();
            $ownedAmount = Money::zero();
            $ownedUsd = Money::zero();
            $owners = [];

            foreach ($group as $sum) {
                $amount = $this->money($sum->amount);
                $usd = $this->money($sum->usd);
                $balance = bcadd($balance, $amount, 2);
                $balanceUsd = bcadd($balanceUsd, $usd, 2);

                if ($sum->project_code === null) {
                    continue;
                }

                $ownedAmount = bcadd($ownedAmount, $amount, 2);
                $ownedUsd = bcadd($ownedUsd, $usd, 2);

                if (bccomp($amount, '0', 2) === 0 && bccomp($usd, '0', 2) === 0) {
                    continue;
                }

                $project = $projects->get($sum->project_code);
                $owners[] = [
                    'project_code' => (string) $sum->project_code,
                    'project_name' => (string) ($project->name ?? $sum->project_code),
                    'general' => (bool) ($project->is_general_account ?? false),
                    'amount' => $amount,
                    'usd' => $usd,
                ];
            }

            if ($projectCodes !== null) {
                $owners = array_values(array_filter($owners, fn (array $owner) => in_array($owner['project_code'], $projectCodes, true)));
            }

            usort($owners, fn (array $a, array $b) => [$b['general'], $a['project_code']] <=> [$a['general'], $b['project_code']]);

            $tills[] = [
                'code' => (string) $code,
                'name_en' => $account->name_en,
                'name_ar' => $account->name_ar,
                'city' => $account->city,
                'currency' => (string) $account->currency,
                'balance' => $balance,
                'balance_usd' => $balanceUsd,
                'owners' => $owners,
                'unowned' => bcsub($balance, $ownedAmount, 2),
                'unowned_usd' => bcsub($balanceUsd, $ownedUsd, 2),
            ];
        }

        usort($tills, fn (array $a, array $b) => [(string) $a['city'], $a['code']] <=> [(string) $b['city'], $b['code']]);

        return $tills;
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return Money::normalize(is_numeric($value) ? (string) $value : null);
    }
}
