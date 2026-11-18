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
use App\Models\GlProject;
use App\Models\MonthClose;
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
 * The entries an agreement makes in the ledger (finance workflow of 2026-11-18, one treasury for the organisation).
 * Both wait for approval like any voucher.
 *
 * - Income: each instalment is booked as income when the agreement is approved, or a conditional one when its
 *   condition is met. A journal voucher debits 1210 receivable from donors and credits 4001 grant income, one pair of
 *   lines per project by its share, in the agreement currency at the agreement's fixed rate.
 * - Receipt: when the money arrives in the transfer company (1161) or a bank, a receipt voucher debits that account
 *   and credits 1210 per project at the fixed rate. The exchange difference between the dollars that really came in and
 *   the dollars at the fixed rate goes to 5503 with no project: it belongs to the organisation. A transfer company's
 *   commission goes to 5504. The receipt is linked to its instalment.
 *
 * After this, the money is the organisation's: moving it between banks, tills and currencies names no project.
 */
class ContractEntries
{
    public const RECEIVABLE = '1210';

    public const INCOME = '4001';

    public const EXCHANGE_DIFFERENCES = '5503';

    /** The group of the bank accounts, under assets. */
    public const BANKS_GROUP = '1165';

    public function __construct(
        private readonly PostVoucher $posting,
        private readonly Currencies $currencies,
        private readonly ContractAuditTrail $trail,
    ) {}

    /**
     * Prepares the income voucher of every instalment that is income now and has none yet.
     *
     * @return list<string> what stopped a voucher, one message per instalment; empty when all were prepared
     */
    public function recogniseDue(Contract $contract, User $by): array
    {
        $problems = [];

        foreach ($contract->instalments()->get() as $instalment) {
            if (! $instalment->isRecognised() || $this->hasRecognition($instalment)) {
                continue;
            }

            try {
                $this->recognise($contract, $instalment, $by);
            } catch (ContractException $refused) {
                $problems[] = __('contracts.recognition_failed', ['number' => $instalment->number, 'reason' => implode(' ', $refused->problems)]);
            }
        }

        return $problems;
    }

    /**
     * The income voucher of one instalment: Dr 1210 / Cr 4001 per project by share, dated the day it became income
     * (the signing, or the day its condition was met), or today when that month is closed.
     *
     * @throws ContractException when the agreement is not approved, the instalment is not income yet, or a posting rule refuses it
     */
    public function recognise(Contract $contract, ContractInstalment $instalment, User $by): Voucher
    {
        abort_unless($instalment->contract_id === $contract->id, 404);

        if (! $contract->isApproved()) {
            throw new ContractException([__('contracts.entry_not_approved')]);
        }

        if (! $instalment->isRecognised()) {
            throw new ContractException([__('contracts.recognition_condition', ['number' => $instalment->number])]);
        }

        if ($this->hasRecognition($instalment)) {
            throw new ContractException([__('contracts.recognition_exists', ['number' => $instalment->number, 'voucher' => $instalment->recognition_voucher_id])]);
        }

        $rate = (string) $contract->fixed_rate;
        $projects = $this->projects($contract);
        $lines = [];

        foreach ($this->splitByShare($contract, (string) $instalment->amount) as $code => $part) {
            $usd = $this->currencies->toUsd($contract->currency, $part, $rate);
            $tags = $this->projectTags($contract, $projects[$code] ?? null, $code);

            $lines[] = new LineDraft(self::RECEIVABLE, $contract->currency, ...$tags, debit: $part, rate: $rate, debitUsd: $usd, debitDonor: $part);
            $lines[] = new LineDraft(self::INCOME, $contract->currency, ...$tags, credit: $part, rate: $rate, creditUsd: $usd, creditDonor: $part);
        }

        $draft = new VoucherDraft(
            type: VoucherType::Journal,
            date: $this->openDay(CarbonImmutable::parse(($instalment->condition_met_on ?? $contract->signed_on ?? $contract->approved_at ?? CarbonImmutable::today())->toDateString())),
            // In the name of who entered the agreement, so the Financial Manager who approved it can approve this voucher too.
            preparedBy: (int) ($contract->created_by ?: $by->getAuthIdentifier()),
            lines: $lines,
            description: __('contracts.recognition_description', ['number' => $instalment->number, 'contract' => $contract->number, 'donor' => (string) $contract->donor?->name_en]),
        );

        return DB::transaction(function () use ($draft, $instalment): Voucher {
            $voucher = $this->post($draft);
            $instalment->update(['recognition_voucher_id' => $voucher->id]);

            return $voucher;
        });
    }

    /** Whether the instalment has an income voucher that still stands (pending or approved, not replaced or deleted). */
    public function hasRecognition(ContractInstalment $instalment): bool
    {
        if ($instalment->recognition_voucher_id === null) {
            return false;
        }

        $status = Voucher::query()->whereKey($instalment->recognition_voucher_id)->value('status');

        return $status !== null && $status !== VoucherStatus::Replaced;
    }

    /**
     * Where an agreement's money can arrive: the transfer company and the bank accounts in the agreement currency or in dollars.
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
            ->whereIn('currency', array_unique([$contract->currency, Currencies::BASE]))
            ->where(fn ($query) => $query->where('code', $company)->orWhereIn('parent_code', $groups))
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
     * @param  numeric-string  $amount  what arrived, in the agreement currency
     * @param  numeric-string  $rate  the rate it was really converted at, in the agreement currency's quoting (1 for dollars)
     * @param  numeric-string  $commissionUsd  what the transfer company kept, in dollars
     * @param  array<string, string>  $split  project code => its part of the amount
     *
     * @throws ContractException when something does not fit or a posting rule refuses the voucher
     */
    public function receive(Contract $contract, ContractInstalment $instalment, string $account, CarbonImmutable $date, string $amount, string $rate, string $commissionUsd, array $split, User $by): Voucher
    {
        abort_unless($instalment->contract_id === $contract->id, 404);

        $destination = $this->destinations($contract)[$account] ?? null;
        $foreign = $contract->currency !== Currencies::BASE;
        $rate = $foreign ? $rate : '1';
        $problems = $this->receiveProblems($contract, $instalment, $destination, $amount, $rate, $commissionUsd, $split);

        if ($problems !== []) {
            throw new ContractException($problems);
        }

        /** @var array{label: string, currency: string, transfer_company: bool} $destination */
        $gross = $this->currencies->toUsd($contract->currency, $amount, $rate);
        $commission = bcadd($commissionUsd, '0', 2);
        $sameCurrency = $destination['currency'] === $contract->currency;
        $donor = (string) $contract->donor?->name_en;
        $hawala = $destination['transfer_company'] && $contract->currency === 'EUR';

        $lines = [new LineDraft(
            accountCode: $account,
            currency: $destination['currency'],
            debit: $sameCurrency && $foreign ? $amount : bcsub($gross, $commission, 2),
            rate: $sameCurrency ? $rate : 1,
            debitUsd: bcsub($gross, $commission, 2),
            contact: $destination['transfer_company'] ? (string) config('ledger.transfer_company_contact') : null,
            donor: $donor !== '' ? $donor : null,
            eurAmount: $hawala ? $amount : null,
            eurRate: $hawala ? $rate : null,
            commissionUsd: $commission,
        )];

        if (bccomp($commission, '0', 2) === 1) {
            $lines[] = new LineDraft(HawalaReceiptIsConsistent::COMMISSION_ACCOUNT, Currencies::BASE, debit: $commission);
        }

        $fixedRate = (string) $contract->fixed_rate;
        $projects = $this->projects($contract);
        $atFixedRate = '0.00';

        foreach ($split as $code => $part) {
            if (bccomp($part, '0', 2) !== 1) {
                continue;
            }

            $usd = $this->currencies->toUsd($contract->currency, $part, $fixedRate);
            $atFixedRate = bcadd($atFixedRate, $usd, 2);
            $lines[] = new LineDraft(self::RECEIVABLE, $contract->currency, ...$this->projectTags($contract, $projects[$code] ?? null, (string) $code),
                credit: $part, rate: $fixedRate, creditUsd: $usd, creditDonor: $part);
        }

        // What came in above the fixed rate is a gain (credit), below it a loss (debit), for the organisation.
        $difference = bcsub($gross, $atFixedRate, 2);

        if (bccomp($difference, '0', 2) !== 0) {
            $gain = bccomp($difference, '0', 2) === 1;
            $size = $gain ? $difference : bcmul($difference, '-1', 2);
            $lines[] = new LineDraft(self::EXCHANGE_DIFFERENCES, Currencies::BASE, debit: $gain ? null : $size, credit: $gain ? $size : null,
                reason: __('contracts.receipt_fx_reason', ['contract' => $contract->number, 'number' => $instalment->number]));
        }

        $draft = new VoucherDraft(
            type: VoucherType::Receipt,
            date: $date,
            preparedBy: (int) $by->getAuthIdentifier(),
            lines: $lines,
            description: __('contracts.receipt_description', ['number' => $instalment->number, 'contract' => $contract->number, 'donor' => $donor]),
        );

        return DB::transaction(function () use ($contract, $instalment, $draft, $amount, $gross, $by): Voucher {
            $voucher = $this->post($draft);

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

    /**
     * @param  array{label: string, currency: string, transfer_company: bool}|null  $destination
     * @param  array<string, string>  $split
     * @return list<string>
     */
    private function receiveProblems(Contract $contract, ContractInstalment $instalment, ?array $destination, string $amount, string $rate, string $commissionUsd, array $split): array
    {
        $problems = [];

        if (! $contract->isApproved() || ! $contract->isOpen()) {
            return [__('contracts.entry_not_approved')];
        }

        if (! $instalment->isRecognised()) {
            $problems[] = __('contracts.receipt_condition_first', ['number' => $instalment->number]);
        } elseif (! $this->hasRecognition($instalment)) {
            $problems[] = __('contracts.receipt_recognition_first', ['number' => $instalment->number]);
        }

        if ($destination === null) {
            $problems[] = __('contracts.receipt_bad_account');
        }

        $remaining = $this->remaining($instalment);

        if (bccomp($amount, $remaining, 2) === 1) {
            $problems[] = __('contracts.receipt_more_than_instalment', ['remaining' => $remaining, 'number' => $instalment->number]);
        }

        if (bccomp($rate, '0', 6) !== 1) {
            $problems[] = __('contracts.receipt_needs_rate');
        }

        if (bccomp($commissionUsd, '0', 2) === -1 || ($destination !== null && $destination['currency'] !== Currencies::BASE && bccomp($commissionUsd, '0', 2) === 1)) {
            $problems[] = __('contracts.receipt_bad_commission');
        } elseif (bccomp($rate, '0', 6) === 1 && bccomp($commissionUsd, $this->currencies->toUsd($contract->currency, $amount, $rate), 2) >= 0) {
            $problems[] = __('contracts.receipt_bad_commission');
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

    /** @throws ContractException with the posting rules' messages */
    private function post(VoucherDraft $draft): Voucher
    {
        try {
            return $this->posting->post($draft);
        } catch (LedgerRuleException $refused) {
            throw new ContractException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
        }
    }

    /** @return array<string, GlProject> */
    private function projects(Contract $contract): array
    {
        return GlProject::query()->whereIn('code', $contract->projects()->pluck('project_code'))->get()->keyBy('code')->all();
    }

    /**
     * The tags a donor line carries: its project, the fund source, the donor and the restriction.
     *
     * @return array{projectCode: string, fundSource: FundSource, donor: string|null, restriction: string|null, donorCurrency: string}
     */
    private function projectTags(Contract $contract, ?GlProject $project, string $code): array
    {
        $donor = (string) $contract->donor?->name_en;

        return [
            'projectCode' => $code,
            'fundSource' => FundSource::DonorGrant,
            'donor' => $donor !== '' ? $donor : null,
            'restriction' => $project?->restriction,
            'donorCurrency' => $contract->currency,
        ];
    }

    /** The day itself, or today when the day's month is already closed. */
    private function openDay(CarbonImmutable $day): CarbonImmutable
    {
        return MonthClose::query()->whereKey($day->format('Y-m'))->exists() ? CarbonImmutable::today() : $day;
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
