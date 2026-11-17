<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractInstalment;
use App\Models\ContractReceipt;
use App\Models\User;
use App\Services\Ledger\Currencies;
use Illuminate\Support\Facades\DB;

/**
 * Which approved receipt vouchers brought an agreement's instalments (finance step of 2026-11-18). A voucher that
 * credits income to the agreement's projects can be linked, whole or in part, to an instalment; an instalment can come in
 * several vouchers. Nothing in the ledger changes: the voucher stays as it was approved, this only says what it was for.
 *
 * For each instalment it tells what arrived (in the agreement currency and in dollars) and the exchange difference: the
 * dollars booked against the dollars the same amount is worth at the agreement's fixed rate.
 */
class ContractReceipts
{
    public function __construct(
        private readonly ContractActuals $actuals,
        private readonly Currencies $currencies,
        private readonly ContractAuditTrail $trail,
    ) {}

    /**
     * The vouchers that can still be linked: their income to the agreement's projects not yet linked to an instalment
     * (of this or another agreement that shares a project).
     *
     * @return array<string, array{date: string, description: string, amount: string, usd: string, account: string|null, available: string}>
     */
    public function candidates(Contract $contract): array
    {
        $vouchers = $this->actuals->incomeByVoucher($contract);
        $linked = $this->linkedByVoucher(array_keys($vouchers));

        $candidates = [];
        foreach ($vouchers as $id => $voucher) {
            $available = bcsub($voucher['amount'], $linked[$id] ?? '0.00', 2);

            if (bccomp($available, '0', 2) === 1) {
                $candidates[$id] = $voucher + ['available' => $available];
            }
        }

        return $candidates;
    }

    /**
     * What arrived for each instalment of the agreement.
     *
     * @return array<int, array{received: string, received_usd: string, expected_usd: string, difference_usd: string, remaining: string, state: string, links: list<array{id: int, voucher: string, date: string, account: string|null, amount: string, usd: string}>}>
     */
    public function status(Contract $contract): array
    {
        $receipts = ContractReceipt::query()->where('contract_id', $contract->id)->orderBy('id')->get();
        $vouchers = $this->actuals->incomeByVoucher($contract, $receipts->pluck('voucher_id')->unique()->values()->all());
        $status = [];

        foreach ($contract->instalments()->get() as $instalment) {
            $mine = $receipts->where('instalment_id', $instalment->id);
            $received = $this->sum($mine->pluck('amount')->all());
            $receivedUsd = $this->sum($mine->pluck('amount_usd')->all());
            $expectedUsd = $this->currencies->toUsd($contract->currency, $received, (string) $contract->fixed_rate);
            $remaining = bcsub((string) $instalment->amount, $received, 2);

            $status[$instalment->id] = [
                'received' => $received,
                'received_usd' => $receivedUsd,
                'expected_usd' => bcadd($expectedUsd, '0', 2),
                'difference_usd' => bcsub($receivedUsd, $expectedUsd, 2),
                'remaining' => $remaining,
                'state' => match (true) {
                    bccomp($received, '0', 2) === 0 => 'none',
                    bccomp($remaining, '0', 2) <= 0 => 'full',
                    default => 'part',
                },
                'links' => array_values($mine->map(fn (ContractReceipt $receipt): array => [
                    'id' => $receipt->id,
                    'voucher' => $receipt->voucher_id,
                    'date' => $vouchers[$receipt->voucher_id]['date'] ?? '',
                    'account' => $vouchers[$receipt->voucher_id]['account'] ?? null,
                    'amount' => (string) $receipt->amount,
                    'usd' => (string) $receipt->amount_usd,
                ])->all()),
            ];
        }

        return $status;
    }

    /**
     * Links an amount of a voucher's income to an instalment, with its share of the voucher's dollars.
     *
     * @throws ContractException when the voucher has not that much left, or the instalment does not need that much
     */
    public function link(Contract $contract, ContractInstalment $instalment, string $voucherId, string $amount, User $by): ContractReceipt
    {
        abort_unless($instalment->contract_id === $contract->id, 404);

        $candidate = $this->candidates($contract)[$voucherId] ?? null;
        $remaining = bcsub((string) $instalment->amount, $this->sum($instalment->receipts()->pluck('amount')->all()), 2);
        $problems = [];

        if ($candidate === null) {
            $problems[] = __('contracts.receipt_not_available', ['voucher' => $voucherId]);
        } elseif (bccomp($amount, $candidate['available'], 2) === 1) {
            $problems[] = __('contracts.receipt_more_than_voucher', ['available' => $candidate['available'], 'voucher' => $voucherId]);
        }

        if (bccomp($amount, $remaining, 2) === 1) {
            $problems[] = __('contracts.receipt_more_than_instalment', ['remaining' => $remaining, 'number' => $instalment->number]);
        }

        if (ContractReceipt::query()->where('instalment_id', $instalment->id)->where('voucher_id', $voucherId)->exists()) {
            $problems[] = __('contracts.receipt_already_linked', ['voucher' => $voucherId, 'number' => $instalment->number]);
        }

        if ($problems !== []) {
            throw new ContractException($problems);
        }

        // The linked part carries its share of the dollars the voucher was booked at.
        $usd = bcdiv(bcmul($candidate['usd'], $amount, 6), $candidate['amount'], 2);

        return DB::transaction(function () use ($contract, $instalment, $voucherId, $amount, $usd, $by): ContractReceipt {
            $receipt = ContractReceipt::query()->create([
                'contract_id' => $contract->id,
                'instalment_id' => $instalment->id,
                'voucher_id' => $voucherId,
                'amount' => $amount,
                'amount_usd' => $usd,
                'linked_by' => (int) $by->getKey(),
            ]);

            $this->trail->receiptLinked($contract, $instalment, $receipt, $by);

            return $receipt;
        });
    }

    public function unlink(Contract $contract, ContractReceipt $receipt, User $by): void
    {
        abort_unless($receipt->contract_id === $contract->id, 404);

        DB::transaction(function () use ($contract, $receipt, $by): void {
            $this->trail->receiptUnlinked($contract, $receipt->instalment()->firstOrFail(), $receipt, $by);
            $receipt->delete();
        });
    }

    /**
     * @param  list<string>  $voucherIds
     * @return array<string, string> voucher id => amount already linked, over every agreement
     */
    private function linkedByVoucher(array $voucherIds): array
    {
        if ($voucherIds === []) {
            return [];
        }

        return ContractReceipt::query()->whereIn('voucher_id', $voucherIds)->get(['voucher_id', 'amount'])
            ->groupBy('voucher_id')
            ->map(fn ($receipts): string => $this->sum($receipts->pluck('amount')->all()))
            ->all();
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
