<?php

namespace App\Services\Loans;

use App\Enums\FundSource;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\MicroInstalment;
use App\Models\MicroLoan;
use App\Models\Voucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Micro project loans (plan Q13, Q15). Opening a loan saves the beneficiary's loan, grant and schedule and posts the
 * disbursement as a payment voucher (CPV): the loan part is debited to 1204, the grant part to the expense account of the
 * chosen budget line, and the cash account is credited with both. Each repayment is a receipt voucher (CRV) that debits the
 * cash account and credits 1204; both lines carry the fund source "Recovered loans", the original donor and that donor's
 * restriction, so recovered money stays under the donor's rules. Vouchers wait for approval like any other. A disbursement
 * above the payment limit needs an approved request (rule 14), which is linked with `$requestId`.
 */
class MicroLoans
{
    public const LOANS_ACCOUNT = '1204';

    /** A beneficiary with this many late instalments, or more, is flagged. */
    public const LATE_FLAG = 3;

    public function __construct(
        private readonly ScheduleGenerator $schedules,
        private readonly PostVoucher $posting,
    ) {}

    /**
     * @return array{loan: MicroLoan, voucher: Voucher}
     *
     * @throws LoanException
     * @throws LedgerRuleException
     */
    public function open(
        string $beneficiary,
        string $projectCode,
        string $loanAmount,
        string $grantAmount,
        string $currency,
        string $rate,
        CarbonImmutable $date,
        int $instalments,
        CarbonImmutable $firstDue,
        string $cashAccount,
        string $budgetLineKey,
        FundSource $fundSource,
        int $preparedBy,
        ?int $requestId = null,
        string $nationalNumber = '',
        string $mobile = '',
        string $partners = '',
        int $intervalMonths = 1,
    ): array {
        $problems = [];
        $loan = $this->amount($loanAmount);
        $grant = $this->amount($grantAmount);

        if (trim($beneficiary) === '') {
            $problems[] = __('loans.problem.beneficiary');
        }

        if (($loan === null && trim($loanAmount) !== '') || ($grant === null && trim($grantAmount) !== '')) {
            $problems[] = __('loans.problem.amount');
        } elseif (bccomp($loan ?? '0', '0', 2) <= 0 && bccomp($grant ?? '0', '0', 2) <= 0) {
            $problems[] = __('loans.problem.nothing');
        }

        $loan ??= '0.00';
        $grant ??= '0.00';

        if (! in_array($currency, ['USD', 'SYP'], true)) {
            $problems[] = __('loans.problem.currency');
        }

        $project = GlProject::query()->find($projectCode);
        if ($project === null || $project->is_general_account) {
            $problems[] = __('loans.problem.project');
        }

        if ($intervalMonths < 1 || $intervalMonths > 12) {
            $problems[] = __('loans.problem.interval');
        }

        if (trim($mobile) !== '' && preg_match('/^[0-9+\-\s()]{5,50}$/', trim($mobile)) !== 1) {
            $problems[] = __('loans.problem.mobile');
        }

        $rate = $currency === 'SYP' ? $this->rate($rate) : '1.000000';
        if ($rate === null) {
            $problems[] = __('loans.problem.rate');
        }

        if (($cashProblem = $this->cashProblem($cashAccount, $currency)) !== null) {
            $problems[] = $cashProblem;
        }

        $budgetLine = null;
        if (bccomp($grant, '0', 2) > 0) {
            $budgetLine = GlBudgetLine::query()->find($budgetLineKey);
            if ($budgetLine === null || $budgetLine->project_code !== $projectCode) {
                $problems[] = __('loans.problem.budget_line');
            }
        }

        $rows = [];
        if (bccomp($loan, '0', 2) > 0 && in_array($currency, ['USD', 'SYP'], true)) {
            try {
                $rows = $this->schedules->equalMonthly($loan, $instalments, $firstDue, $currency, $intervalMonths);
            } catch (LoanException $e) {
                array_push($problems, ...$e->problems);
            }
        }

        if ($problems !== [] || $project === null || $rate === null) {
            throw new LoanException($problems === [] ? [__('loans.problem.amount')] : $problems);
        }

        $details = [
            'national_number' => $this->optional($nationalNumber, 30), 'mobile' => $this->optional($mobile, 50),
            'partners' => $this->optional($partners, 255), 'payment_interval_months' => $intervalMonths, 'first_payment_date' => $firstDue->toDateString(),
        ];

        return DB::transaction(function () use ($beneficiary, $project, $loan, $grant, $currency, $rate, $date, $rows, $cashAccount, $budgetLine, $fundSource, $preparedBy, $requestId, $details) {
            $draft = $this->disbursement($beneficiary, $project, $loan, $grant, $currency, $rate, $date, $cashAccount, $budgetLine, $fundSource, $preparedBy, $requestId);
            $voucher = $this->posting->post($draft);

            $record = MicroLoan::query()->create([
                'beneficiary' => trim($beneficiary), 'project_code' => $project->code,
                'loan_amount' => $loan, 'grant_amount' => $grant, 'currency' => $currency,
            ] + $details);
            foreach ($rows as $row) {
                $record->instalments()->create(['due_date' => $row['due_date'], 'amount' => $row['amount']]);
            }

            return ['loan' => $record, 'voucher' => $voucher];
        });
    }

    /**
     * Receives a payment from the beneficiary, in the loan's currency. It settles the oldest instalments first, so a payment
     * can part-pay one instalment or run over into the next; more than the loan still owes is refused (the money would have
     * no instalment to cover). The receipt voucher is saved as pending approval and marks every instalment it touched.
     *
     * @throws LoanException
     * @throws LedgerRuleException
     */
    public function receive(MicroLoan $loan, CarbonImmutable $date, string $amount, string $rate, string $cashAccount, int $preparedBy): Voucher
    {
        $problems = [];
        $paid = $this->amount($amount) ?? '0.00';

        if (bccomp($paid, '0', 2) <= 0) {
            $problems[] = __('loans.problem.payment');
        }

        $rate = $loan->currency === 'SYP' ? $this->rate($rate) : '1.000000';
        if ($rate === null) {
            $problems[] = __('loans.problem.rate');
            $rate = '1.000000';
        }

        if (($cashProblem = $this->cashProblem($cashAccount, $loan->currency)) !== null) {
            $problems[] = $cashProblem;
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        return DB::transaction(function () use ($loan, $date, $paid, $rate, $cashAccount, $preparedBy) {
            $open = $loan->instalments()->lockForUpdate()->get()->filter(fn (MicroInstalment $i) => bccomp($i->outstanding(), '0', 2) > 0)->values();
            $owed = '0.00';
            foreach ($open as $instalment) {
                $owed = bcadd($owed, $instalment->outstanding(), 2);
            }

            if (bccomp($paid, $owed, 2) > 0) {
                throw new LoanException([__('loans.problem.overpayment', ['owed' => $owed, 'currency' => $loan->currency])]);
            }

            $voucher = $this->posting->post($this->receipt($loan, $date, $paid, $rate, $cashAccount, $preparedBy));

            $left = $paid;
            foreach ($open as $instalment) {
                if (bccomp($left, '0', 2) <= 0) {
                    break;
                }
                $part = bccomp($left, $instalment->outstanding(), 2) < 0 ? $left : $instalment->outstanding();
                $instalment->update(['paid_amount' => bcadd(Money::normalize($instalment->paid_amount), $part, 2), 'paid_voucher_id' => $voucher->id]);
                $left = bcsub($left, $part, 2);
            }

            return $voucher;
        });
    }

    /**
     * What a loan is behind with on a date: the instalments due on or before it that are not fully paid.
     *
     * @return array{amount: string, instalments: int, days: int}
     */
    public function arrears(MicroLoan $loan, CarbonImmutable $asAt): array
    {
        $amount = '0.00';
        $count = 0;
        $oldest = null;

        foreach ($loan->instalments as $instalment) {
            $owing = $instalment->outstanding();
            if ($instalment->due_date->toDateString() > $asAt->toDateString() || bccomp($owing, '0', 2) <= 0) {
                continue;
            }
            $amount = bcadd($amount, $owing, 2);
            $count++;
            $oldest ??= $instalment->due_date;
        }

        return ['amount' => $amount, 'instalments' => $count, 'days' => $oldest === null ? 0 : (int) CarbonImmutable::parse($oldest->toDateString())->diffInDays($asAt)];
    }

    /**
     * How many instalments are late on a date: due before it and not fully paid. This is the Windows program's "late"
     * count (an unpaid or incomplete instalment whose due date has passed); a beneficiary with three or more is flagged.
     */
    public function latePayments(MicroLoan $loan, CarbonImmutable $asAt): int
    {
        return $loan->instalments->filter(
            fn (MicroInstalment $instalment): bool => $instalment->due_date->toDateString() < $asAt->toDateString() && bccomp($instalment->outstanding(), '0', 2) > 0,
        )->count();
    }

    private function optional(string $text, int $max): ?string
    {
        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    /**
     * @param  numeric-string  $loan
     * @param  numeric-string  $grant
     * @param  numeric-string  $rate
     */
    private function disbursement(string $beneficiary, GlProject $project, string $loan, string $grant, string $currency, string $rate, CarbonImmutable $date, string $cashAccount, ?GlBudgetLine $budgetLine, FundSource $fundSource, int $preparedBy, ?int $requestId): VoucherDraft
    {
        $total = bcadd($loan, $grant, 2);
        $totalUsd = $this->toUsd($total, $currency, $rate);
        $loanUsd = bccomp($grant, '0', 2) > 0 ? $this->toUsd($loan, $currency, $rate) : $totalUsd;
        $grantUsd = bcsub($totalUsd, $loanUsd, 2);
        $name = trim($beneficiary);
        $lines = [];

        if (bccomp($loan, '0', 2) > 0) {
            $lines[] = new LineDraft(
                accountCode: self::LOANS_ACCOUNT, currency: $currency, debit: $loan, rate: $rate, debitUsd: $loanUsd,
                projectCode: $project->code, fundSource: $fundSource, contact: $name, donor: $project->donor,
                restriction: $project->restriction, donorCurrency: $project->donor_currency, debitDonor: $this->donorAmount($loanUsd, $project),
            );
        }

        if ($budgetLine !== null) {
            $lines[] = new LineDraft(
                accountCode: $budgetLine->account_code, currency: $currency, debit: $grant, rate: $rate, debitUsd: $grantUsd,
                projectCode: $project->code, budgetLineKey: $budgetLine->key, donor: $project->donor,
                restriction: $project->restriction, donorCurrency: $project->donor_currency, debitDonor: $this->donorAmount($grantUsd, $project),
            );
        }

        $lines[] = new LineDraft(
            accountCode: $cashAccount, currency: $currency, credit: $total, rate: $rate, creditUsd: $totalUsd,
            projectCode: $project->code, fundSource: $fundSource, donor: $project->donor,
            restriction: $project->restriction, donorCurrency: $project->donor_currency, creditDonor: $this->donorAmount($totalUsd, $project),
        );

        return new VoucherDraft(
            type: VoucherType::Payment, date: $date, preparedBy: $preparedBy, lines: $lines,
            description: __('loans.disbursement_description', ['beneficiary' => $name, 'project' => $project->code]),
            requestId: $requestId,
        );
    }

    /**
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate
     */
    private function receipt(MicroLoan $loan, CarbonImmutable $date, string $amount, string $rate, string $cashAccount, int $preparedBy): VoucherDraft
    {
        $project = GlProject::query()->findOrFail($loan->project_code);
        $usd = $this->toUsd($amount, $loan->currency, $rate);
        $donorAmount = $this->donorAmount($usd, $project);

        return new VoucherDraft(
            type: VoucherType::Receipt, date: $date, preparedBy: $preparedBy,
            lines: [
                new LineDraft(
                    accountCode: $cashAccount, currency: $loan->currency, debit: $amount, rate: $rate, debitUsd: $usd,
                    projectCode: $project->code, fundSource: FundSource::RecoveredLoans, donor: $project->donor,
                    restriction: $project->restriction, donorCurrency: $project->donor_currency, debitDonor: $donorAmount,
                ),
                new LineDraft(
                    accountCode: self::LOANS_ACCOUNT, currency: $loan->currency, credit: $amount, rate: $rate, creditUsd: $usd,
                    projectCode: $project->code, fundSource: FundSource::RecoveredLoans, contact: $loan->beneficiary, donor: $project->donor,
                    restriction: $project->restriction, donorCurrency: $project->donor_currency, creditDonor: $donorAmount,
                ),
            ],
            description: __('loans.receipt_description', ['beneficiary' => $loan->beneficiary, 'project' => $project->code]),
        );
    }

    private function cashProblem(string $code, string $currency): ?string
    {
        $account = GlAccount::query()->find($code);

        if ($account === null || ! $account->is_cash || $account->currency !== $currency || preg_match((string) config('ledger.in_transit_pattern'), $account->code) === 1) {
            return __('loans.problem.cash_account', ['currency' => $currency]);
        }

        return null;
    }

    /**
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate
     * @return numeric-string
     */
    private function toUsd(string $amount, string $currency, string $rate): string
    {
        return $currency === 'USD' ? Money::normalize($amount) : Money::normalize(bcdiv($amount, $rate, 6));
    }

    /**
     * @param  numeric-string  $usd
     * @return numeric-string
     */
    private function donorAmount(string $usd, GlProject $project): string
    {
        $donorRate = Money::normalize($project->donor_rate, 6);

        if ($project->donor_currency === 'USD' || bccomp($donorRate, '0', 6) <= 0) {
            return $usd;
        }

        return Money::normalize(bcdiv($usd, $donorRate, 6));
    }

    /** @return numeric-string|null */
    private function amount(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }

    /** @return numeric-string|null */
    private function rate(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        if (preg_match('/^\d+(\.\d+)?$/', $clean) !== 1) {
            return null;
        }

        $rate = Money::normalize($clean, 6);

        return bccomp($rate, '0', 6) > 0 ? $rate : null;
    }
}
