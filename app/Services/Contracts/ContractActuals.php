<?php

namespace App\Services\Contracts;

use App\Enums\AccountClass;
use App\Enums\VoucherStatus;
use App\Models\Contract;
use App\Services\Ledger\Currencies;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * What has happened on an agreement's projects so far, read from approved vouchers (finance step of 2026-11-17):
 * what was spent on each budget line (expense lines that name the line) and what was received for each project
 * (the receipts linked to the agreement's instalments). Amounts are in the agreement currency: a line's own
 * amount when it is in that currency, else its donor amount when the donor currency is that currency, else its dollar
 * amount at the agreement's fixed rate.
 */
class ContractActuals
{
    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @param  list<string>  $budgetLineKeys
     * @return array<string, string> budget line key => spent (debit minus credit), in the agreement currency
     */
    public function spentByLine(Contract $contract, array $budgetLineKeys): array
    {
        if ($budgetLineKeys === []) {
            return [];
        }

        $rows = $this->approvedLines()
            ->where('a.class', AccountClass::Expenses->value)
            ->whereIn('l.budget_line_key', $budgetLineKeys)
            ->get(['l.budget_line_key as group_key', 'l.currency', 'l.donor_currency', 'l.debit_orig', 'l.credit_orig', 'l.debit_donor', 'l.credit_donor', 'l.debit_usd', 'l.credit_usd']);

        return $this->sumByGroup($contract, $rows->all(), debitSide: true);
    }

    /**
     * What arrived for each project: the credits to 1210 receivable from donors, per project, in the approved receipt
     * vouchers linked to the agreement's instalments.
     *
     * @return array<string, string> project code => received, in the agreement currency
     */
    public function receivedByProject(Contract $contract): array
    {
        $codes = $contract->projects()->pluck('project_code')->all();
        if ($codes === []) {
            return [];
        }

        $rows = $this->approvedLines()
            ->where('l.account_code', ContractEntries::RECEIVABLE)
            ->whereIn('l.project_code', $codes)
            ->whereIn('l.voucher_id', DB::table('gl_contract_receipts')->where('contract_id', $contract->id)->select('voucher_id'))
            ->get(['l.project_code as group_key', 'l.currency', 'l.donor_currency', 'l.debit_orig', 'l.credit_orig', 'l.debit_donor', 'l.credit_donor', 'l.debit_usd', 'l.credit_usd']);

        return $this->sumByGroup($contract, $rows->all(), debitSide: false);
    }

    private function approvedLines(): Builder
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at');
    }

    /**
     * @param  list<stdClass>  $rows
     * @return array<string, string>
     */
    private function sumByGroup(Contract $contract, array $rows, bool $debitSide): array
    {
        $sums = [];
        foreach ($rows as $row) {
            $amount = $this->inAgreementCurrency($contract, $row);
            $sums[$row->group_key] = bcadd($sums[$row->group_key] ?? '0.00', $debitSide ? $amount : bcmul($amount, '-1', 2), 2);
        }

        return $sums;
    }

    /** One line's debit minus credit, in the agreement currency. */
    private function inAgreementCurrency(Contract $contract, stdClass $row): string
    {
        $net = fn (string $side): string => bcsub($this->money($row->{'debit_'.$side}), $this->money($row->{'credit_'.$side}), 2);

        if ($row->currency === $contract->currency) {
            return $net('orig');
        }

        if ($row->donor_currency === $contract->currency) {
            return $net('donor');
        }

        $usd = $net('usd');

        return $contract->currency === Currencies::BASE ? $usd : $this->currencies->fromUsd($contract->currency, $usd, (string) $contract->fixed_rate);
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
