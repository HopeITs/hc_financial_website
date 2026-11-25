<?php

namespace App\Services\Loans;

use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\MicroInstalment;
use App\Models\MicroLoan;
use App\Models\MicroPayment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\NetAssets;
use App\Services\Ledger\PendingVoucherChanges;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\TreasuryException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /** Micro projects grants: where a written-off loan goes (finance decision of 2026-11-21). */
    public const GRANTS_ACCOUNT = '5413';

    /** The organisation's exchange differences, between a pound loan's book rate and the day's rate it is repaid at. */
    public const EXCHANGE_DIFFERENCES = '5503';

    /** A beneficiary with this many late instalments, or more, is flagged. */
    public const LATE_FLAG = 3;

    public function __construct(
        private readonly ScheduleGenerator $schedules,
        private readonly PostVoucher $posting,
        private readonly DailyRates $dailyRates,
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
        ?string $fundFunder = null,
        ?string $fundCity = null,
    ): array {
        $problems = [];
        $loan = $this->amount($loanAmount);
        $grant = $this->amount($grantAmount);
        // Funded from an instalment fund (finance decision of 2026-11-22): no project, the funder and the city only.
        $fromFund = $fundFunder !== null && trim($fundFunder) !== '';
        $cash = GlAccount::query()->find($cashAccount);
        $payCurrency = (string) $cash?->currency;

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

        $project = $fromFund ? null : GlProject::query()->find($projectCode);
        if (! $fromFund && ($project === null || $project->is_general_account)) {
            $problems[] = __('loans.problem.project');
        }

        if ($intervalMonths < 1 || $intervalMonths > 12) {
            $problems[] = __('loans.problem.interval');
        }

        if (trim($mobile) !== '' && preg_match('/^[0-9+\-\s()]{5,50}$/', trim($mobile)) !== 1) {
            $problems[] = __('loans.problem.mobile');
        }

        // A loan in dollars can be paid in pounds, at the rate of the invoice (the month's by default).
        $rate = $currency === 'SYP' || $payCurrency === 'SYP' ? $this->rate($rate) : '1.000000';
        if ($rate === null) {
            $problems[] = __('loans.problem.rate');
        }

        if (($cashProblem = $this->cashProblem($cashAccount, $currency === 'USD' ? ['USD', 'SYP'] : [$currency])) !== null) {
            $problems[] = $cashProblem;
        }

        $budgetLine = null;
        if (bccomp($grant, '0', 2) > 0 && ! $fromFund) {
            $budgetLine = GlBudgetLine::query()->find($budgetLineKey);
            if ($budgetLine === null || $budgetLine->project_code !== $projectCode) {
                $problems[] = __('loans.problem.budget_line');
            }
        }

        if ($fromFund) {
            $fundSource = FundSource::RecoveredLoans;

            if ($cash !== null && (string) $cash->city !== (string) $fundCity) {
                $problems[] = __('loans.problem.fund_city', ['city' => (string) $fundCity]);
            } elseif ($cash !== null && $rate !== null && in_array($currency, ['USD', 'SYP'], true)) {
                $paid = $this->paidAmount(bcadd($loan, $grant, 2), $currency, $payCurrency, $rate);
                $available = app(InstalmentFunds::class)->available((string) $fundFunder, (string) $fundCity, $payCurrency, $date);

                if (bccomp($paid, $available, 2) > 0) {
                    $problems[] = __('loans.problem.fund_short', ['available' => $available, 'currency' => $payCurrency]);
                }
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

        if ($problems !== [] || (! $fromFund && $project === null) || $rate === null || $cash === null) {
            throw new LoanException($problems === [] ? [__('loans.problem.amount')] : $problems);
        }

        $details = [
            'national_number' => $this->optional($nationalNumber, 30), 'mobile' => $this->optional($mobile, 50),
            'partners' => $this->optional($partners, 255), 'payment_interval_months' => $intervalMonths, 'first_payment_date' => $firstDue->toDateString(),
        ];
        $funder = $fromFund ? trim((string) $fundFunder) : (string) $project?->donor;
        $city = $fromFund ? (string) $fundCity : ((string) $cash->city !== '' ? (string) $cash->city : (string) $project?->city);
        $tags = $this->fundTags($project, $funder, $city);

        return DB::transaction(function () use ($beneficiary, $project, $loan, $grant, $currency, $rate, $date, $rows, $cash, $budgetLine, $fundSource, $preparedBy, $requestId, $details, $funder, $city, $tags) {
            $draft = $this->disbursement($beneficiary, $project, $loan, $grant, $currency, $rate, $date, $cash, $budgetLine, $fundSource, $preparedBy, $requestId, $tags);
            $voucher = $this->posting->post($draft);

            $record = MicroLoan::query()->create([
                'beneficiary' => trim($beneficiary), 'project_code' => $project?->code,
                'loan_amount' => $loan, 'grant_amount' => $grant, 'currency' => $currency,
                'disbursement_voucher_id' => $voucher->id, 'funder' => $funder !== '' ? $funder : null, 'city' => $city !== '' ? $city : null,
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
     * Pounds (finance decision of 2026-11-21): the cash takes the day's approved rate, by itself; the loan leaves 1204 at
     * the rate it was given at, so a repaid loan is nothing in pounds and nothing in dollars, and the gap between the two
     * rates is the organisation's exchange difference (5503).
     *
     * @throws LoanException
     * @throws LedgerRuleException
     */
    public function receive(MicroLoan $loan, CarbonImmutable $date, string $amount, string $cashAccount, int $preparedBy): Voucher
    {
        $problems = [];
        $paid = $this->amount($amount) ?? '0.00';

        if (bccomp($paid, '0', 2) <= 0) {
            $problems[] = __('loans.problem.payment');
        }

        $rate = '1.000000';
        if ($loan->currency !== 'USD') {
            try {
                $rate = $this->dailyRates->requireRate($loan->currency, $date);
            } catch (TreasuryException $missing) {
                array_push($problems, ...$missing->problems);
            }
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

            $book = $this->bookPart($loan, $paid, $owed, $rate);
            $voucher = $this->posting->post($this->receipt($loan, $date, $paid, $rate, $book, $cashAccount, $preparedBy));
            $allocations = $this->settle($open, $paid, 'paid_amount', $voucher->id);

            MicroPayment::query()->create([
                'loan_id' => $loan->id, 'voucher_id' => $voucher->id, 'kind' => MicroPayment::RECEIPT,
                'amount' => $paid, 'usd' => $book['usd'], 'allocations' => $allocations,
            ]);

            return $voucher;
        });
    }

    /**
     * What the beneficiary still owes, to receive it all at once and close the loan.
     *
     * @return numeric-string
     */
    public function payOffAmount(MicroLoan $loan): string
    {
        return $loan->load('instalments')->owed();
    }

    /**
     * Forgives what is left of a loan (finance decision of 2026-11-21): a journal voucher moves it from 1204 to the
     * project's micro-projects grants line (5413), at the loan's book dollars, so the donor report shows it as a grant.
     * The instalments it covers are marked written off.
     *
     * @throws LoanException
     * @throws LedgerRuleException
     */
    public function writeOff(MicroLoan $loan, CarbonImmutable $date, string $reason, int $preparedBy): Voucher
    {
        $loan->load('instalments');
        $owed = $loan->owed();
        $project = $loan->project_code !== null ? GlProject::query()->find($loan->project_code) : null;
        // A loan from a project goes to that project's grants budget line; one from an instalment fund straight to 5413.
        $grantLine = $project !== null ? GlBudgetLine::query()->where('project_code', $project->code)->where('account_code', self::GRANTS_ACCOUNT)->orderBy('key')->first() : null;
        $problems = [];

        if (bccomp($owed, '0', 2) <= 0) {
            $problems[] = __('loans.problem.nothing_owed');
        }

        if (trim($reason) === '') {
            $problems[] = __('loans.problem.write_off_reason');
        }

        if ($project !== null && $grantLine === null) {
            $problems[] = __('loans.problem.no_grant_line', ['project' => $loan->project_code, 'account' => self::GRANTS_ACCOUNT]);
        }

        $fallback = '1.000000';
        if ($loan->currency !== 'USD' && $this->book($loan) === null) {
            try {
                $fallback = $this->dailyRates->requireRate($loan->currency, $date);
            } catch (TreasuryException $missing) {
                array_push($problems, ...$missing->problems);
            }
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        return DB::transaction(function () use ($loan, $date, $reason, $preparedBy, $owed, $project, $grantLine, $fallback) {
            $book = $this->bookPart($loan, $owed, $owed, $fallback);
            $fundSource = $this->fundSourceOf($loan);
            $tags = $this->loanTags($loan);
            $name = $loan->beneficiary;

            $voucher = $this->posting->post(new VoucherDraft(
                type: VoucherType::Journal, date: $date, preparedBy: $preparedBy,
                lines: [
                    new LineDraft(
                        ...$tags,
                        accountCode: $grantLine?->account_code ?? self::GRANTS_ACCOUNT, currency: $loan->currency, debit: $owed, rate: $book['rate'], debitUsd: $book['usd'],
                        budgetLineKey: $grantLine?->key, contact: $name, debitDonor: $this->donorAmount($book['usd'], $project), reason: trim($reason),
                    ),
                    new LineDraft(
                        ...$tags,
                        accountCode: self::LOANS_ACCOUNT, currency: $loan->currency, credit: $owed, rate: $book['rate'], creditUsd: $book['usd'],
                        fundSource: $fundSource, contact: $name, creditDonor: $this->donorAmount($book['usd'], $project),
                    ),
                ],
                description: __('loans.write_off_description', ['beneficiary' => $name, 'project' => $this->loanLabel($loan)]),
            ));

            $open = $loan->instalments()->lockForUpdate()->get()->filter(fn (MicroInstalment $i) => bccomp($i->outstanding(), '0', 2) > 0)->values();
            $allocations = $this->settle($open, $owed, 'written_off_amount', null);

            MicroPayment::query()->create([
                'loan_id' => $loan->id, 'voucher_id' => $voucher->id, 'kind' => MicroPayment::WRITE_OFF,
                'amount' => $owed, 'usd' => $book['usd'], 'allocations' => $allocations,
            ]);

            return $voucher;
        });
    }

    /**
     * Spreads what is still owed over a new schedule (finance request of 2026-11-21). An instalment already part paid
     * keeps what it received and closes; the unpaid ones give way to the new ones.
     *
     * @throws LoanException
     */
    public function reschedule(MicroLoan $loan, int $count, CarbonImmutable $firstDue, int $intervalMonths): void
    {
        $loan->load('instalments');
        $owed = $loan->owed();

        if (bccomp($owed, '0', 2) <= 0) {
            throw new LoanException([__('loans.problem.nothing_owed')]);
        }

        $rows = $this->schedules->equalMonthly($owed, $count, $firstDue, $loan->currency, $intervalMonths);

        DB::transaction(function () use ($loan, $rows, $intervalMonths): void {
            foreach ($loan->instalments as $instalment) {
                if (bccomp($instalment->outstanding(), '0', 2) <= 0) {
                    continue;
                }

                $settled = bcadd(Money::normalize($instalment->paid_amount), Money::normalize($instalment->written_off_amount ?? '0'), 2);
                if (bccomp($settled, '0', 2) > 0) {
                    $instalment->update(['amount' => $settled]);
                } else {
                    $instalment->delete();
                }
            }

            foreach ($rows as $row) {
                $loan->instalments()->create(['due_date' => $row['due_date'], 'amount' => $row['amount']]);
            }

            $loan->update(['payment_interval_months' => $intervalMonths]);
        });
    }

    /** Changes the beneficiary's details. The vouchers already made keep the name they were made with. */
    public function updateDetails(MicroLoan $loan, string $beneficiary, string $nationalNumber, string $mobile, string $partners): void
    {
        $problems = [];

        if (trim($beneficiary) === '') {
            $problems[] = __('loans.problem.beneficiary');
        }

        if (trim($mobile) !== '' && preg_match('/^[0-9+\-\s()]{5,50}$/', trim($mobile)) !== 1) {
            $problems[] = __('loans.problem.mobile');
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        $loan->update([
            'beneficiary' => mb_substr(trim($beneficiary), 0, 255), 'national_number' => $this->optional($nationalNumber, 30),
            'mobile' => $this->optional($mobile, 50), 'partners' => $this->optional($partners, 255),
        ]);
    }

    /**
     * Deletes a loan whose disbursement voucher still waits for approval, with that voucher (finance request of
     * 2026-11-21). A loan given (its voucher approved) or already repaid in part is not deleted: its vouchers are reversed.
     *
     * @throws LoanException
     */
    public function delete(MicroLoan $loan, User $by, string $reason): void
    {
        $voucher = $loan->disbursement;

        if ($loan->payments()->exists()) {
            throw new LoanException([__('loans.problem.delete_paid')]);
        }

        if ($voucher === null) {
            throw new LoanException([__('loans.problem.delete_old')]);
        }

        if (! $voucher->trashed() && $voucher->status !== VoucherStatus::PendingApproval) {
            throw new LoanException([__('loans.problem.delete_given', ['voucher' => $voucher->id])]);
        }

        DB::transaction(function () use ($loan, $voucher, $by, $reason): void {
            if (! $voucher->trashed()) {
                try {
                    app(PendingVoucherChanges::class)->delete($voucher, $by, $reason);
                } catch (LedgerRuleException $refused) {
                    throw new LoanException(array_map(fn ($violation): string => (string) $violation->message, $refused->violations));
                }
            }

            MicroLoan::query()->whereKey($loan->id)->delete();
        });
    }

    /**
     * A voucher was deleted or rejected (PendingVoucherChanges, VoucherApprovals): the repayment or write-off it made is
     * given back to the instalments, and a loan it disbursed, with nothing repaid yet, goes with it.
     */
    public function forgetVoucher(Voucher $voucher): void
    {
        // Until the migration of 2026-11-21 has run there is nothing to give back, and a rejection must still work.
        if (! Schema::hasTable('gl_micro_payments')) {
            return;
        }

        foreach (MicroPayment::query()->where('voucher_id', $voucher->id)->get() as $payment) {
            $column = $payment->kind === MicroPayment::WRITE_OFF ? 'written_off_amount' : 'paid_amount';

            foreach ($payment->allocations as $allocation) {
                $instalment = MicroInstalment::query()->find($allocation['instalment']);
                if ($instalment === null) {
                    continue;
                }

                $left = bcsub(Money::normalize($instalment->{$column} ?? '0'), Money::normalize($allocation['amount']), 2);
                $instalment->update([
                    $column => bccomp($left, '0', 2) < 0 ? '0.00' : $left,
                    'paid_voucher_id' => $instalment->paid_voucher_id === $voucher->id ? null : $instalment->paid_voucher_id,
                ]);
            }

            $payment->delete();
        }

        MicroLoan::query()->where('disbursement_voucher_id', $voucher->id)->whereDoesntHave('payments')->delete();

        // A micro-project file's purchase or closing (from the migration of 2026-11-23 on).
        if (Schema::hasTable('gl_micro_purchases')) {
            app(MicroPurchases::class)->forgetVoucher($voucher);
        }
    }

    /**
     * Settles the oldest open instalments with an amount, on the given column.
     *
     * @param  iterable<MicroInstalment>  $open
     * @param  numeric-string  $amount
     * @return list<array{instalment: int, amount: string}>
     */
    private function settle(iterable $open, string $amount, string $column, ?string $voucherId): array
    {
        $left = $amount;
        $allocations = [];

        foreach ($open as $instalment) {
            if (bccomp($left, '0', 2) <= 0) {
                break;
            }

            $part = bccomp($left, $instalment->outstanding(), 2) < 0 ? $left : $instalment->outstanding();
            $instalment->update([$column => bcadd(Money::normalize($instalment->{$column} ?? '0'), $part, 2)] + ($voucherId !== null ? ['paid_voucher_id' => $voucherId] : []));
            $allocations[] = ['instalment' => $instalment->id, 'amount' => $part];
            $left = bcsub($left, $part, 2);
        }

        return $allocations;
    }

    /**
     * The loan as 1204 holds it, from its disbursement voucher: the amount and its dollars. Null for a loan opened
     * before 2026-11-21, which has no disbursement voucher on record.
     *
     * @return array{orig: string, usd: string}|null
     */
    private function book(MicroLoan $loan): ?array
    {
        // A loan opened from a micro-project file keeps the dollars of its repayable part (MicroPurchases::close).
        if ($loan->book_usd !== null && bccomp(Money::normalize($loan->loan_amount), '0', 2) > 0) {
            return ['orig' => Money::normalize($loan->loan_amount), 'usd' => Money::normalize($loan->book_usd)];
        }

        if ($loan->disbursement_voucher_id === null) {
            return null;
        }

        $line = VoucherLine::query()->where('voucher_id', $loan->disbursement_voucher_id)->where('account_code', self::LOANS_ACCOUNT)->first();

        return $line === null || bccomp((string) $line->debit_orig, '0', 2) <= 0 ? null : ['orig' => (string) $line->debit_orig, 'usd' => (string) $line->debit_usd];
    }

    /**
     * What an amount taken off the loan is worth in its book dollars, and at what rate. The amount that clears the loan
     * takes all its book dollars left, so a repaid loan is nothing in dollars too. A loan with no book uses the given rate.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $owed  what the loan owed before this amount
     * @param  numeric-string  $fallback
     * @return array{rate: string, usd: string}
     */
    private function bookPart(MicroLoan $loan, string $amount, string $owed, string $fallback): array
    {
        $book = $this->book($loan);

        if ($book === null) {
            return ['rate' => $fallback, 'usd' => $this->toUsd($amount, $loan->currency, $fallback)];
        }

        $rate = $loan->currency === 'USD' ? '1.000000' : Money::normalize(bcdiv($book['orig'], $book['usd'], 6), 6);

        if (bccomp($amount, $owed, 2) === 0) {
            $taken = array_reduce(MicroPayment::query()->where('loan_id', $loan->id)->pluck('usd')->all(), fn (string $sum, mixed $usd): string => bcadd($sum, (string) $usd, 2), '0.00');

            return ['rate' => $rate, 'usd' => bcsub(Money::normalize($book['usd']), $taken, 2)];
        }

        return ['rate' => $rate, 'usd' => $this->toUsd($amount, $loan->currency, $rate)];
    }

    /** The fund source the loan was given from, kept on its 1204 line; the donor's grant when unknown. */
    private function fundSourceOf(MicroLoan $loan): FundSource
    {
        if ($loan->fund_source !== null && FundSource::tryFrom($loan->fund_source) !== null) {
            return FundSource::from($loan->fund_source);
        }

        $value = $loan->disbursement_voucher_id !== null
            ? VoucherLine::query()->where('voucher_id', $loan->disbursement_voucher_id)->where('account_code', self::LOANS_ACCOUNT)->value('fund_source')
            : null;

        return $value instanceof FundSource ? $value : (FundSource::tryFrom((string) $value) ?? FundSource::DonorGrant);
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
     * The disbursement, in the loan's currency, and the cash out in the cash account's currency: the same amount, or,
     * for a loan in dollars paid in pounds, its pounds at the invoice's rate. A project's loan puts the loan on 1204 and
     * the grant on its budget line. A loan from an instalment fund spends the whole amount on the grants (5413) and
     * moves the share to repay to 1204 in the same voucher (finance decision of 2026-11-25).
     *
     * @param  numeric-string  $loan
     * @param  numeric-string  $grant
     * @param  numeric-string  $rate
     * @param  array{projectCode: string|null, donor: string|null, restriction: string|null, donorCurrency: string|null, city: string|null}  $tags
     */
    private function disbursement(string $beneficiary, ?GlProject $project, string $loan, string $grant, string $currency, string $rate, CarbonImmutable $date, GlAccount $cash, ?GlBudgetLine $budgetLine, FundSource $fundSource, int $preparedBy, ?int $requestId, array $tags): VoucherDraft
    {
        $total = bcadd($loan, $grant, 2);
        $totalUsd = $this->toUsd($total, $currency, $rate);
        $loanUsd = bccomp($grant, '0', 2) > 0 ? $this->toUsd($loan, $currency, $rate) : $totalUsd;
        $grantUsd = bcsub($totalUsd, $loanUsd, 2);
        $lineRate = $currency === 'USD' ? '1.000000' : $rate;
        $payCurrency = (string) $cash->currency;
        $name = trim($beneficiary);
        $lines = [];
        $cashLine = new LineDraft(
            ...$tags,
            accountCode: $cash->code, currency: $payCurrency, credit: $this->paidAmount($total, $currency, $payCurrency, $rate), rate: $payCurrency === 'USD' ? '1.000000' : $rate, creditUsd: $totalUsd,
            fundSource: $fundSource, creditDonor: $this->donorAmount($totalUsd, $project),
        );

        // A loan from an instalment fund is funded like a micro-project file (finance decision of 2026-11-25): the whole
        // amount is spent on the grants, then the share to repay moves from the grants to the loan, in the same voucher.
        if ($project === null) {
            $lines[] = new LineDraft(
                ...$tags,
                accountCode: self::GRANTS_ACCOUNT, currency: $currency, debit: $total, rate: $lineRate, debitUsd: $totalUsd,
                contact: $name, debitDonor: $this->donorAmount($totalUsd, $project),
            );
            $lines[] = $cashLine;

            if (bccomp($loan, '0', 2) > 0) {
                $lines[] = new LineDraft(
                    ...$tags,
                    accountCode: self::LOANS_ACCOUNT, currency: $currency, debit: $loan, rate: $lineRate, debitUsd: $loanUsd,
                    fundSource: $fundSource, contact: $name, debitDonor: $this->donorAmount($loanUsd, $project),
                );
                $lines[] = new LineDraft(
                    ...$tags,
                    accountCode: self::GRANTS_ACCOUNT, currency: $currency, credit: $loan, rate: $lineRate, creditUsd: $loanUsd,
                    contact: $name, creditDonor: $this->donorAmount($loanUsd, $project),
                );
            }

            return new VoucherDraft(
                type: VoucherType::Payment, date: $date, preparedBy: $preparedBy, lines: $lines,
                description: __('loans.disbursement_description', ['beneficiary' => $name, 'project' => $tags['donor'].' · '.$tags['city']]),
                requestId: $requestId,
            );
        }

        if (bccomp($loan, '0', 2) > 0) {
            $lines[] = new LineDraft(
                ...$tags,
                accountCode: self::LOANS_ACCOUNT, currency: $currency, debit: $loan, rate: $lineRate, debitUsd: $loanUsd,
                fundSource: $fundSource, contact: $name, debitDonor: $this->donorAmount($loanUsd, $project),
            );
        }

        if (bccomp($grant, '0', 2) > 0) {
            $lines[] = new LineDraft(
                ...$tags,
                accountCode: $budgetLine?->account_code ?? self::GRANTS_ACCOUNT, currency: $currency, debit: $grant, rate: $lineRate, debitUsd: $grantUsd,
                budgetLineKey: $budgetLine?->key, contact: $name, debitDonor: $this->donorAmount($grantUsd, $project),
            );
        }

        $lines[] = $cashLine;

        return new VoucherDraft(
            type: VoucherType::Payment, date: $date, preparedBy: $preparedBy, lines: $lines,
            description: __('loans.disbursement_description', ['beneficiary' => $name, 'project' => $project?->code ?? $tags['donor'].' · '.$tags['city']]),
            requestId: $requestId,
        );
    }

    /**
     * What is paid out in the cash account's currency for an amount in the loan's: the same, or the pounds of a loan in
     * dollars at the invoice's rate.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate
     * @return numeric-string
     */
    private function paidAmount(string $amount, string $currency, string $payCurrency, string $rate): string
    {
        return $currency === 'USD' && $payCurrency === 'SYP' ? Money::normalize(bcmul($amount, $rate, 6)) : Money::normalize($amount);
    }

    /**
     * The tags a loan's lines carry: its project's (donor, restriction), or, for a loan from an instalment fund, the
     * funder and the city with the donor's restriction, since recovered money stays under the donor's rules.
     *
     * @return array{projectCode: string|null, donor: string|null, restriction: string|null, donorCurrency: string|null, city: string|null}
     */
    private function fundTags(?GlProject $project, ?string $funder, ?string $city): array
    {
        if ($project !== null) {
            return ['projectCode' => $project->code, 'donor' => $project->donor, 'restriction' => $project->restriction, 'donorCurrency' => $project->donor_currency, 'city' => null];
        }

        return ['projectCode' => null, 'donor' => $funder !== '' ? $funder : null, 'restriction' => NetAssets::RESTRICTED, 'donorCurrency' => null, 'city' => $city !== '' ? $city : null];
    }

    /** How a loan is named in a voucher's description: its project, or its funder and city. */
    private function loanLabel(MicroLoan $loan): string
    {
        return $loan->project_code ?? trim($loan->funder.' · '.$loan->city, ' ·');
    }

    /**
     * A loan's tags, from its project or its fund.
     *
     * @return array{projectCode: string|null, donor: string|null, restriction: string|null, donorCurrency: string|null, city: string|null}
     */
    private function loanTags(MicroLoan $loan): array
    {
        $project = $loan->project_code !== null ? GlProject::query()->find($loan->project_code) : null;

        return $this->fundTags($project, $loan->funder, $loan->city);
    }

    /**
     * The receipt: the cash at the day's rate, 1204 at the loan's book rate, and the gap on 5503.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate  the day's approved rate (1 for dollars)
     * @param  array{rate: string, usd: string}  $book  what the amount takes off the loan's book dollars
     */
    private function receipt(MicroLoan $loan, CarbonImmutable $date, string $amount, string $rate, array $book, string $cashAccount, int $preparedBy): VoucherDraft
    {
        $project = $loan->project_code !== null ? GlProject::query()->find($loan->project_code) : null;
        $tags = $this->loanTags($loan);
        $usd = $this->toUsd($amount, $loan->currency, $rate);
        $difference = bcsub($usd, $book['usd'], 2);
        $lines = [
            new LineDraft(
                ...$tags,
                accountCode: $cashAccount, currency: $loan->currency, debit: $amount, rate: $rate, debitUsd: $usd,
                fundSource: FundSource::RecoveredLoans, debitDonor: $this->donorAmount($usd, $project),
            ),
            new LineDraft(
                ...$tags,
                accountCode: self::LOANS_ACCOUNT, currency: $loan->currency, credit: $amount, rate: $book['rate'], creditUsd: $book['usd'],
                fundSource: FundSource::RecoveredLoans, contact: $loan->beneficiary, creditDonor: $this->donorAmount($book['usd'], $project),
            ),
        ];

        if (bccomp($difference, '0', 2) !== 0) {
            $gain = bccomp($difference, '0', 2) === 1;
            $size = Money::abs($difference);
            $lines[] = new LineDraft(self::EXCHANGE_DIFFERENCES, 'USD', debit: $gain ? null : $size, credit: $gain ? $size : null);
        }

        return new VoucherDraft(
            type: VoucherType::Receipt, date: $date, preparedBy: $preparedBy, lines: $lines,
            description: __('loans.receipt_description', ['beneficiary' => $loan->beneficiary, 'project' => $this->loanLabel($loan)]),
        );
    }

    /** @param  string|list<string>  $currencies  the currencies the cash account may be in */
    private function cashProblem(string $code, string|array $currencies): ?string
    {
        $currencies = (array) $currencies;
        $account = GlAccount::query()->find($code);

        if ($account === null || ! $account->is_cash || ! in_array((string) $account->currency, $currencies, true) || preg_match((string) config('ledger.in_transit_pattern'), $account->code) === 1) {
            return __('loans.problem.cash_account', ['currency' => implode(' / ', $currencies)]);
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
    private function donorAmount(string $usd, ?GlProject $project): string
    {
        if ($project === null) {
            return $usd;
        }

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
