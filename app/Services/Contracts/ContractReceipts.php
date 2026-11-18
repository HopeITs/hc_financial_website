<?php

namespace App\Services\Contracts;

use App\Enums\VoucherStatus;
use App\Models\Contract;
use App\Models\ContractReceipt;
use App\Services\Ledger\Currencies;
use Illuminate\Support\Facades\DB;

/**
 * What arrived for each instalment of an agreement (finance workflow of 2026-11-18). A receipt is prepared from the
 * instalment itself (ContractEntries::receive) and linked to it; it counts as received once its voucher is approved, and
 * shows as waiting for approval until then. A replaced or deleted voucher no longer counts.
 *
 * For each instalment it tells what arrived (in the agreement currency and in dollars) and the exchange difference: the
 * dollars that really came in against the dollars the same amount is worth at the agreement's fixed rate.
 */
class ContractReceipts
{
    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @return array<int, array{received: string, received_usd: string, expected_usd: string, difference_usd: string, pending: string, remaining: string, state: string, links: list<array{id: int, voucher: string, date: string, account: string|null, amount: string, usd: string, approved: bool}>}>
     */
    public function status(Contract $contract): array
    {
        $receipts = ContractReceipt::query()
            ->join('gl_vouchers as v', 'v.id', '=', 'gl_contract_receipts.voucher_id')
            ->where('gl_contract_receipts.contract_id', $contract->id)
            ->where('v.status', '!=', VoucherStatus::Replaced->value)
            ->whereNull('v.deleted_at')
            ->orderBy('gl_contract_receipts.id')
            ->get(['gl_contract_receipts.*', 'v.status as voucher_status', 'v.date as voucher_date']);
        $accounts = $this->accounts($receipts->pluck('voucher_id')->unique()->values()->all());
        $status = [];

        foreach ($contract->instalments()->get() as $instalment) {
            $mine = $receipts->where('instalment_id', $instalment->id);
            $approved = $mine->where('voucher_status', VoucherStatus::Approved->value);
            $received = $this->sum($approved->pluck('amount')->all());
            $receivedUsd = $this->sum($approved->pluck('amount_usd')->all());
            $pending = $this->sum($mine->where('voucher_status', '!=', VoucherStatus::Approved->value)->pluck('amount')->all());
            $expectedUsd = $this->currencies->toUsd($contract->currency, $received, (string) $contract->fixed_rate);
            $outstanding = bcsub((string) $instalment->amount, $received, 2);

            $status[$instalment->id] = [
                'received' => $received,
                'received_usd' => $receivedUsd,
                'expected_usd' => bcadd($expectedUsd, '0', 2),
                'difference_usd' => bcsub($receivedUsd, $expectedUsd, 2),
                'pending' => $pending,
                'remaining' => bcsub($outstanding, $pending, 2),
                'state' => match (true) {
                    bccomp($received, '0', 2) === 0 => 'none',
                    bccomp($outstanding, '0', 2) <= 0 => 'full',
                    default => 'part',
                },
                'links' => array_values($mine->map(fn (ContractReceipt $receipt): array => [
                    'id' => $receipt->id,
                    'voucher' => $receipt->voucher_id,
                    'date' => substr((string) $receipt->getAttribute('voucher_date'), 0, 10),
                    'account' => $accounts[$receipt->voucher_id] ?? null,
                    'amount' => (string) $receipt->amount,
                    'usd' => (string) $receipt->amount_usd,
                    'approved' => $receipt->getAttribute('voucher_status') === VoucherStatus::Approved->value,
                ])->all()),
            ];
        }

        return $status;
    }

    /**
     * @param  list<string>  $voucherIds
     * @return array<string, string> voucher id => the cash or bank account it first debits, with its name
     */
    private function accounts(array $voucherIds): array
    {
        if ($voucherIds === []) {
            return [];
        }

        $arabic = app()->getLocale() === 'ar';
        $accounts = [];

        $lines = DB::table('gl_voucher_lines as l')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->whereIn('l.voucher_id', $voucherIds)
            ->where('a.is_cash', true)
            ->where('l.debit_orig', '>', 0)
            ->orderBy('l.line_no')
            ->get(['l.voucher_id', 'a.code', 'a.name_en', 'a.name_ar']);

        foreach ($lines as $line) {
            $accounts[(string) $line->voucher_id] ??= $line->code.' · '.($arabic ? $line->name_ar : $line->name_en);
        }

        return $accounts;
    }

    /**
     * @param  array<int, mixed>  $amounts
     * @return numeric-string
     */
    private function sum(array $amounts): string
    {
        return array_reduce($amounts, fn (string $total, mixed $amount): string => bcadd($total, (string) $amount, 2), '0.00');
    }
}
