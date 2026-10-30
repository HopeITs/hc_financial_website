<?php

namespace App\Services\Ledger;

use App\Enums\AccountClass;
use App\Models\GlProject;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The donor report of one project (F-13), from approved vouchers: what the donor sent, in its own currency and in
 * dollars, and how it was spent against each budget line. The budget, the spending, what remains and the share spent
 * come from ProjectBalances::budgetVsActual, in each line's own currency, so this report and the project page agree;
 * the dollars spent sit beside them.
 *
 * The project's real rate is what its receipts were really worth: dollars received over donor currency received
 * (for a euro donor, the dollars each euro brought after the transfer company's rate).
 *
 * @phpstan-type Line array{key: string, name: string, account_code: string, currency: string, budget: numeric-string, actual: numeric-string, remaining: numeric-string, percent: numeric-string, usd: numeric-string}
 * @phpstan-type Receipt array{date: string, voucher: string, description: string, donor: numeric-string, usd: numeric-string, eur: numeric-string|null, eur_rate: numeric-string|null}
 * @phpstan-type Report array{project: GlProject, lines: list<Line>, totals: array<string, array{budget: numeric-string, actual: numeric-string, remaining: numeric-string, percent: numeric-string}>, spent_usd: numeric-string, receipts: list<Receipt>, received: array{donor: numeric-string, usd: numeric-string}, rate: numeric-string|null, balance: array{donor: numeric-string, usd: numeric-string}, after_end: bool}
 */
class DonorReport
{
    public function __construct(private readonly ProjectBalances $balances) {}

    /**
     * @return Report|null
     */
    public function of(string $projectCode): ?array
    {
        $project = GlProject::query()->where('is_general_account', false)->find($projectCode);
        if ($project === null) {
            return null;
        }

        $spentUsd = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')->whereNull('v.deleted_at')
            ->where('l.project_code', $projectCode)->whereNotNull('l.budget_line_key')
            ->groupBy('l.budget_line_key')
            ->selectRaw('l.budget_line_key as line_key, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->pluck('usd', 'line_key');

        $lines = [];
        $totals = [];
        $usdTotal = Money::zero();
        foreach ($this->balances->budgetVsActual($projectCode) as $row) {
            $usd = Money::normalize($spentUsd[$row['key']] ?? '0');
            $lines[] = $row + ['usd' => $usd];
            $usdTotal = bcadd($usdTotal, $usd, 2);

            $total = $totals[$row['currency']] ?? ['budget' => Money::zero(), 'actual' => Money::zero()];
            $totals[$row['currency']] = ['budget' => bcadd($total['budget'], $row['budget'], 2), 'actual' => bcadd($total['actual'], $row['actual'], 2)];
        }
        foreach ($totals as $currency => $total) {
            $totals[$currency] += [
                'remaining' => bcsub($total['budget'], $total['actual'], 2),
                'percent' => bccomp($total['budget'], '0', 2) > 0 ? bcdiv(bcmul($total['actual'], '100', 4), $total['budget'], 1) : '0.0',
            ];
        }

        $receipts = $this->receipts($projectCode, $project->donor_currency);
        $received = ['donor' => Money::zero(), 'usd' => Money::zero()];
        foreach ($receipts as $receipt) {
            $received = ['donor' => bcadd($received['donor'], $receipt['donor'], 2), 'usd' => bcadd($received['usd'], $receipt['usd'], 2)];
        }

        $spentDonor = $this->spentInDonorCurrency($projectCode, $project->donor_currency);

        return [
            'project' => $project,
            'lines' => $lines,
            'totals' => $totals,
            'spent_usd' => $usdTotal,
            'receipts' => $receipts,
            'received' => $received,
            'rate' => bccomp($received['donor'], '0', 2) > 0 ? Money::normalize(bcdiv($received['usd'], $received['donor'], 6), 6) : null,
            'balance' => ['donor' => bcsub($received['donor'], $spentDonor, 2), 'usd' => bcsub($received['usd'], $usdTotal, 2)],
            'after_end' => $project->end_date !== null && now()->startOfDay()->gt($project->end_date),
        ];
    }

    /**
     * The donor's receipts: the income lines of the project, each voucher once, in the donor's currency and in dollars,
     * with the euros sent and their rate when the transfer recorded them.
     *
     * @return list<Receipt>
     */
    private function receipts(string $projectCode, string $donorCurrency): array
    {
        $rows = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')->whereNull('v.deleted_at')
            ->where('l.project_code', $projectCode)
            ->where('a.class', AccountClass::Income->value)
            ->groupBy('v.id', 'v.date', 'v.description')
            ->orderBy('v.date')->orderBy('v.id')
            ->selectRaw('v.id as voucher, v.date, v.description, sum(l.credit_usd) - sum(l.debit_usd) as usd, sum(l.credit_donor) - sum(l.debit_donor) as donor')
            ->get();

        // The euros sent and their rate sit on the receipt's dollar debit to the transfer company (1161), not on its income line.
        $euros = DB::table('gl_voucher_lines')->whereIn('voucher_id', $rows->pluck('voucher')->all())->whereNotNull('eur_amount')
            ->groupBy('voucher_id')->selectRaw('voucher_id, sum(eur_amount) as eur, max(eur_rate) as eur_rate')->get()->keyBy('voucher_id');

        $receipts = [];
        foreach ($rows as $row) {
            $usd = Money::normalize($row->usd);
            // A dollar donor's lines carry no separate donor amount: the dollars are the donor's currency.
            $donor = $donorCurrency === Currencies::BASE ? $usd : Money::normalize($row->donor);

            $receipts[] = [
                'date' => substr((string) $row->date, 0, 10),
                'voucher' => (string) $row->voucher,
                'description' => (string) $row->description,
                'donor' => $donor,
                'usd' => $usd,
                'eur' => isset($euros[$row->voucher]) ? Money::normalize($euros[$row->voucher]->eur) : null,
                'eur_rate' => isset($euros[$row->voucher]) ? Money::normalize($euros[$row->voucher]->eur_rate, 6) : null,
            ];
        }

        return $receipts;
    }

    /** @return numeric-string what the budget lines spent, in the donor's currency */
    private function spentInDonorCurrency(string $projectCode, string $donorCurrency): string
    {
        $sum = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', 'approved')->whereNull('v.deleted_at')
            ->where('l.project_code', $projectCode)->whereNotNull('l.budget_line_key')
            ->selectRaw($donorCurrency === Currencies::BASE ? 'sum(l.debit_usd) - sum(l.credit_usd) as spent' : 'sum(l.debit_donor) - sum(l.credit_donor) as spent')
            ->value('spent');

        return Money::normalize($sum ?? '0');
    }
}
