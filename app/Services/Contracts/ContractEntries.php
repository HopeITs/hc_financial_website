<?php

namespace App\Services\Contracts;

use App\Enums\AccountLevel;
use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Contract;
use App\Models\ContractInstalment;
use App\Models\ContractReceipt;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\Currencies;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\Rules\HawalaReceiptIsConsistent;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The entry an agreement makes in the ledger (finance decision of 2026-11-20): nothing when the agreement or its
 * instalments are set, and one receipt voucher when money arrives. The donor's grant (4001, one line per project with the
 * donor) is credited and the account the money reached is debited, in the money's own currency:
 *
 * - a bank account in the agreement currency: euros stay euros until they are withdrawn to the transfer company, valued
 *   in the books at the rate given (the day's approved rate by default);
 * - the transfer company (1161, dollars): a non-dollar instalment is converted at the rate really obtained.
 *
 * A fee kept on the way (the bank's or the transfer company's) is charged either to the organisation (5501 bank
 * charges, 5504 transfer company commission, no project) or to a budget line of the agreement's projects, as chosen.
 * The receipt is linked to its instalment and counts once its voucher is approved.
 */
class ContractEntries
{
    public const INCOME = '4001';

    public const BANK_CHARGES = '5501';

    /** The group of the bank accounts, under assets. */
    public const BANKS_GROUP = '1165';

    /** The fee is the organisation's, not a project's. */
    public const FEE_TO_ORGANISATION = 'organisation';

    public function __construct(
        private readonly PostVoucher $posting,
        private readonly Currencies $currencies,
        private readonly ContractAuditTrail $trail,
    ) {}

    /**
     * Where an agreement's money can arrive: the bank accounts in the agreement currency, and the transfer company.
     *
     * @return array<string, array{label: string, currency: string, transfer_company: bool}>
     */
    public function destinations(Contract $contract): array
    {
        $company = (string) config('ledger.transfer_company_account');
        $groups = GlAccount::query()->where('parent_code', self::BANKS_GROUP)->pluck('code')->all();
        $arabic = app()->getLocale() === 'ar';

        return GlAccount::query()
            ->where('is_cash', true)->where('is_active', true)->where('level', AccountLevel::Posting->value)
            ->where(fn ($query) => $query->where('code', $company)->orWhere(fn ($banks) => $banks->whereIn('parent_code', $groups)->where('currency', $contract->currency)))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (GlAccount $account): array => [$account->code => [
                'label' => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en),
                'currency' => (string) $account->currency,
                'transfer_company' => $account->code === $company,
            ]])
            ->all();
    }

    /**
     * The budget lines a fee can be charged to: those of the agreement's projects.
     *
     * @return array<string, string> budget line key => "PROJECT · name"
     */
    public function feeBudgetLines(Contract $contract): array
    {
        return GlBudgetLine::query()->whereIn('project_code', $contract->projects()->pluck('project_code'))->orderBy('project_code')->orderBy('name')->get()
            ->mapWithKeys(fn (GlBudgetLine $line): array => [$line->key => $line->project_code.' · '.$line->name])
            ->all();
    }

    /**
     * What is still to arrive for an instalment: its amount less every receipt prepared for it that still stands.
     *
     * @return numeric-string
     */
    public function remaining(ContractInstalment $instalment): string
    {
        $prepared = ContractReceipt::query()
            ->join('gl_vouchers as v', 'v.id', '=', 'gl_contract_receipts.voucher_id')
            ->where('gl_contract_receipts.instalment_id', $instalment->id)
            ->where('v.status', '!=', VoucherStatus::Replaced->value)
            ->whereNull('v.deleted_at')
            ->pluck('gl_contract_receipts.amount')
            ->all();

        return bcsub((string) $instalment->amount, $this->sum($prepared), 2);
    }

    /**
     * Prepares the receipt voucher of money that arrived for an instalment, and links it to the instalment.
     *
     * @param  numeric-string  $amount  what the donor sent, in the agreement currency
     * @param  numeric-string  $rate  the agreement currency's rate, in its quoting (1 for dollars): the day's rate into a bank, the rate obtained into the transfer company
     * @param  numeric-string  $fee  what the bank or the transfer company kept, in the currency of the account reached
     * @param  string  $feeTo  self::FEE_TO_ORGANISATION or the key of a budget line of the agreement's projects
     * @param  array<string, string>  $split  project code => its part of the amount
     *
     * @throws ContractException when something does not fit or a posting rule refuses the voucher
     */
    public function receive(Contract $contract, ContractInstalment $instalment, string $account, CarbonImmutable $date, string $amount, string $rate, string $fee, string $feeTo, array $split, User $by): Voucher
    {
        abort_unless($instalment->contract_id === $contract->id, 404);

        $destination = $this->destinations($contract)[$account] ?? null;
        $rate = $contract->currency === Currencies::BASE ? '1' : $rate;
        $feeLine = $feeTo === self::FEE_TO_ORGANISATION ? null : GlBudgetLine::query()->find($feeTo);
        $problems = $this->receiveProblems($contract, $instalment, $destination, $amount, $rate, $fee, $feeTo, $feeLine, $split);

        if ($problems !== []) {
            throw new ContractException($problems);
        }

        /** @var array{label: string, currency: string, transfer_company: bool} $destination */
        $currency = $destination['currency'];
        $gross = $this->currencies->toUsd($contract->currency, $amount, $rate);
        $fee = bcadd($fee, '0', 2);
        $feeUsd = $currency === $contract->currency ? $this->currencies->toUsd($currency, $fee, $rate) : $fee;
        $lineRate = $currency === $contract->currency ? $rate : '1';
        $arrived = $currency === $contract->currency ? bcsub($amount, $fee, 2) : bcsub($gross, $fee, 2);
        $donor = (string) $contract->donor?->name_en;
        $projects = GlProject::query()->whereIn('code', array_keys($split))->get()->keyBy('code');

        $lines = [new LineDraft(
            accountCode: $account,
            currency: $currency,
            debit: $arrived,
            rate: $lineRate,
            debitUsd: bcsub($gross, $feeUsd, 2),
            contact: $destination['transfer_company'] ? (string) config('ledger.transfer_company_contact') : null,
            donor: $donor !== '' ? $donor : null,
        )];

        if (bccomp($fee, '0', 2) === 1) {
            $lines[] = $this->feeLine($destination, $feeLine, $currency, $fee, $lineRate, $feeUsd, $contract);
        }

        foreach ($split as $code => $part) {
            if (bccomp($part, '0', 2) !== 1) {
                continue;
            }

            $project = $projects->get($code);
            $lines[] = new LineDraft(
                accountCode: self::INCOME,
                currency: $contract->currency,
                credit: $part,
                rate: $rate,
                creditUsd: $this->currencies->toUsd($contract->currency, $part, $rate),
                projectCode: (string) $code,
                fundSource: FundSource::DonorGrant,
                donor: $donor !== '' ? $donor : null,
                restriction: $project?->restriction,
                donorCurrency: $contract->currency,
                creditDonor: $part,
            );
        }

        $draft = new VoucherDraft(
            type: VoucherType::Receipt,
            date: $date,
            preparedBy: (int) $by->getAuthIdentifier(),
            lines: $lines,
            description: __('contracts.receipt_description', ['number' => $instalment->number, 'contract' => $contract->number, 'donor' => $donor]),
        );

        return DB::transaction(function () use ($contract, $instalment, $draft, $amount, $gross, $by): Voucher {
            try {
                $voucher = $this->posting->post($draft);
            } catch (LedgerRuleException $refused) {
                throw new ContractException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
            }

            $receipt = ContractReceipt::query()->create([
                'contract_id' => $contract->id,
                'instalment_id' => $instalment->id,
                'voucher_id' => $voucher->id,
                'amount' => $amount,
                'amount_usd' => $gross,
                'linked_by' => (int) $by->getKey(),
            ]);

            $this->trail->receiptLinked($contract, $instalment, $receipt, $by);

            return $voucher;
        });
    }

    /**
     * An amount split over the agreement's projects by their shares; the last project takes what rounding leaves.
     *
     * @param  numeric-string  $amount
     * @return array<string, string> project code => part
     */
    public function splitByShare(Contract $contract, string $amount): array
    {
        $shares = $contract->projects()->orderBy('id')->get(['project_code', 'amount']);
        $total = (string) $contract->amount;
        $split = [];
        $left = bcadd($amount, '0', 2);

        foreach ($shares->values() as $index => $share) {
            $part = $index === $shares->count() - 1 || bccomp($total, '0', 2) !== 1
                ? $left
                : bcdiv(bcmul($amount, (string) $share->amount, 6), $total, 2);
            $split[$share->project_code] = $part;
            $left = bcsub($left, $part, 2);
        }

        return $split;
    }

    /** @param  array{transfer_company: bool}  $destination */
    private function feeLine(array $destination, ?GlBudgetLine $budgetLine, string $currency, string $fee, string $rate, string $feeUsd, Contract $contract): LineDraft
    {
        if ($budgetLine === null) {
            return new LineDraft(
                accountCode: $destination['transfer_company'] ? HawalaReceiptIsConsistent::COMMISSION_ACCOUNT : self::BANK_CHARGES,
                currency: $currency, debit: $fee, rate: $rate, debitUsd: $feeUsd,
            );
        }

        $project = GlProject::query()->find($budgetLine->project_code);

        return new LineDraft(
            accountCode: $budgetLine->account_code,
            currency: $currency, debit: $fee, rate: $rate, debitUsd: $feeUsd,
            projectCode: $budgetLine->project_code, budgetLineKey: $budgetLine->key, fundSource: FundSource::DonorGrant,
            donor: $project?->donor, restriction: $project?->restriction, donorCurrency: $contract->currency,
            debitDonor: $currency === $contract->currency ? $fee : $this->currencies->fromUsd($contract->currency, $feeUsd, $rate),
        );
    }

    /**
     * @param  array{label: string, currency: string, transfer_company: bool}|null  $destination
     * @param  array<string, string>  $split
     * @return list<string>
     */
    private function receiveProblems(Contract $contract, ContractInstalment $instalment, ?array $destination, string $amount, string $rate, string $fee, string $feeTo, ?GlBudgetLine $feeLine, array $split): array
    {
        if (! $contract->isApproved() || ! $contract->isOpen()) {
            return [__('contracts.entry_not_approved')];
        }

        $problems = [];

        if ($destination === null) {
            $problems[] = __('contracts.receipt_bad_account', ['currency' => $contract->currency]);
        }

        $remaining = $this->remaining($instalment);

        if (bccomp($amount, $remaining, 2) === 1) {
            $problems[] = __('contracts.receipt_more_than_instalment', ['remaining' => $remaining, 'number' => $instalment->number]);
        }

        if (bccomp($rate, '0', 6) !== 1) {
            $problems[] = __('contracts.receipt_needs_rate');
        }

        $feeLimit = $destination !== null && $destination['currency'] !== $contract->currency && bccomp($rate, '0', 6) === 1
            ? $this->currencies->toUsd($contract->currency, $amount, $rate)
            : $amount;

        if (bccomp($fee, '0', 2) === -1 || bccomp($fee, $feeLimit, 2) >= 0) {
            $problems[] = __('contracts.receipt_bad_fee');
        } elseif (bccomp($fee, '0', 2) === 1 && $feeTo !== self::FEE_TO_ORGANISATION && ($feeLine === null || ! array_key_exists($feeLine->key, $this->feeBudgetLines($contract)))) {
            $problems[] = __('contracts.receipt_bad_fee_line');
        }

        $codes = $contract->projects()->pluck('project_code')->all();
        $unknown = array_diff(array_keys($split), $codes);
        $negative = array_filter($split, fn (string $part): bool => bccomp($part, '0', 2) === -1);

        if ($unknown !== [] || $negative !== []) {
            $problems[] = __('contracts.receipt_bad_split');
        } elseif (bccomp($this->sum(array_values($split)), $amount, 2) !== 0) {
            $problems[] = __('contracts.receipt_split_total', ['total' => $this->sum(array_values($split)), 'amount' => bcadd($amount, '0', 2)]);
        }

        return $problems;
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
