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
use App\Models\MicroPurchase;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\NetAssets;
use App\Services\Ledger\PendingVoucherChanges;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDocuments;
use App\Services\Ledger\VoucherDraft;
use App\Services\Ledger\Violation;
use App\Services\Treasury\MonthlyRates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The purchases of an approved micro-project file and its closing (finance decisions of 2026-11-23). The centre buys
 * the equipment itself; the beneficiary gets no cash.
 *
 * - An invoice paid directly to the merchant: a payment voucher crediting the cash and debiting the whole invoice to
 *   the grants (5413) (finance decisions of 2026-11-25): on the project's budget line for a project's file, so the
 *   donor's report lists each invoice in full; with the funder and city for a file of an instalment fund.
 * - An advance to the purchasing officer: a payment voucher on 1201 for the officer. Its invoices are journal vouchers
 *   from 1201 to the same debit, at the advance's rate; what is left goes back to the cash with a receipt voucher.
 * - Pounds take the invoice's rate, the month's by default. A loan in dollars bought in pounds takes the pounds' dollars;
 *   a loan in pounds is bought in pounds.
 * - The file's approval stands for the request a payment above the limit needs (rule 14).
 * - Closing, when the beneficiary signs the receipt protocol: the loan is the recovery share of the invoices' total. A
 *   journal voucher moves it from the grants to 1204 (with no budget line, so the donor's report keeps the full
 *   spending); for invoices booked on 1204 before 2026-11-25 it moves the grant share to the grants instead. The instalments start two months after the signature by default.
 */
class MicroPurchases
{
    /** The first instalment falls this many months after the receipt protocol is signed (finance decision of 2026-11-23). */
    public const FIRST_DUE_MONTHS = 2;

    /** An extra amount above this share of the approved amount must be explained to the Program Manager and the Financial Manager. */
    public const EXTRA_SHARE = '0.10';

    /** Instalments a schedule may hold at most. */
    public const MAX_INSTALMENTS = 240;

    /** The purchasing officer's advances (operational advances). */
    public const ADVANCES_ACCOUNT = '1201';

    /** The cash a file's purchases are paid from, whatever the loan's currency (finance decision of 2026-11-28). */
    public const PAY_CURRENCIES = ['USD', 'SYP'];

    public function __construct(
        private readonly PostVoucher $posting,
        private readonly MonthlyRates $monthlyRates,
        private readonly InstalmentFunds $funds,
    ) {}

    /**
     * Pays an invoice directly to the merchant from a cash account.
     *
     * @param  array{date?: string, cash_account?: string, amount?: string, rate?: string, merchant?: string, number?: string, description_ar?: string, description_en?: string, is_seizure?: bool}  $form
     *
     * @throws LoanException
     */
    public function payInvoice(User $by, MicroLoan $file, array $form, ?UploadedFile $scan = null): MicroPurchase
    {
        $this->assertApproved($file);
        [$date, $cash, $amount, $rate, $problems] = $this->cashMovement($file, $form, self::PAY_CURRENCIES);
        $problems = [...$problems, ...$this->invoiceProblems($form), ...$this->grantLineProblems($file)];

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        /** @var CarbonImmutable $date */
        /** @var GlAccount $cash */
        /** @var numeric-string $amount */
        /** @var numeric-string $rate */
        $usd = $this->toUsd($amount, (string) $cash->currency, $rate);
        $loanAmount = $this->inLoanCurrency($file, $amount, (string) $cash->currency, $usd, $rate);
        $tags = $this->tags($file);
        $project = $this->project($file);

        return DB::transaction(function () use ($by, $file, $form, $scan, $date, $cash, $amount, $rate, $usd, $loanAmount, $tags, $project): MicroPurchase {
            $voucher = $this->post(new VoucherDraft(
                type: VoucherType::Payment, date: $date, preparedBy: (int) $by->getAuthIdentifier(),
                description: __('micro.voucher.invoice', ['beneficiary' => $file->beneficiary, 'merchant' => trim((string) ($form['merchant'] ?? '')), 'number' => trim((string) ($form['number'] ?? ''))]),
                lines: [
                    $this->loanLine($file, $loanAmount, $rate, $usd, $tags, $project),
                    new LineDraft(
                        ...$tags,
                        accountCode: $cash->code, currency: (string) $cash->currency, credit: $amount, rate: $cash->currency === 'USD' ? '1.000000' : $rate, creditUsd: $usd,
                        fundSource: $this->fundSource($file), creditDonor: $this->donorAmount($usd, $project),
                    ),
                ],
            ), $file);
            app(VoucherDocuments::class)->record($voucher, 'invoice', (string) ($form['number'] ?? ''), (string) ($form['description_en'] ?? ''));

            return $this->record($by, $file, $voucher, MicroPurchase::INVOICE, $date, (string) $cash->currency, $amount, $rate, $loanAmount, $usd, $form, $scan, ['cash_account' => $cash->code]);
        });
    }

    /**
     * Gives the purchasing officer an advance for the file's purchases.
     *
     * @param  array{date?: string, cash_account?: string, amount?: string, rate?: string, person?: string}  $form
     *
     * @throws LoanException
     */
    public function giveAdvance(User $by, MicroLoan $file, array $form): MicroPurchase
    {
        $this->assertApproved($file);
        [$date, $cash, $amount, $rate, $problems] = $this->cashMovement($file, $form, self::PAY_CURRENCIES);
        $person = trim((string) ($form['person'] ?? ''));

        if ($person === '') {
            $problems[] = __('micro.problem.person');
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        /** @var CarbonImmutable $date */
        /** @var GlAccount $cash */
        /** @var numeric-string $amount */
        /** @var numeric-string $rate */
        $currency = (string) $cash->currency;
        $usd = $this->toUsd($amount, $currency, $rate);
        $lineRate = $currency === 'USD' ? '1.000000' : $rate;
        $tags = $this->tags($file);
        $project = $this->project($file);

        return DB::transaction(function () use ($by, $file, $date, $cash, $amount, $rate, $usd, $currency, $lineRate, $person, $tags, $project): MicroPurchase {
            $voucher = $this->post(new VoucherDraft(
                type: VoucherType::Payment, date: $date, preparedBy: (int) $by->getAuthIdentifier(),
                description: __('micro.voucher.advance', ['beneficiary' => $file->beneficiary, 'person' => $person]),
                lines: [
                    new LineDraft(
                        ...$tags,
                        accountCode: self::ADVANCES_ACCOUNT, currency: $currency, debit: $amount, rate: $lineRate, debitUsd: $usd,
                        fundSource: $this->fundSource($file), contact: $person, debitDonor: $this->donorAmount($usd, $project),
                    ),
                    new LineDraft(
                        ...$tags,
                        accountCode: $cash->code, currency: $currency, credit: $amount, rate: $lineRate, creditUsd: $usd,
                        fundSource: $this->fundSource($file), creditDonor: $this->donorAmount($usd, $project),
                    ),
                ],
            ), $file);

            return $this->record($by, $file, $voucher, MicroPurchase::ADVANCE, $date, $currency, $amount, $rate, '0.00', $usd, [], null, ['person' => mb_substr($person, 0, 255), 'cash_account' => $cash->code]);
        });
    }

    /**
     * Books an invoice the purchasing officer paid from an advance, at the advance's rate.
     *
     * @param  array{date?: string, amount?: string, merchant?: string, number?: string, description_ar?: string, description_en?: string, is_seizure?: bool}  $form
     *
     * @throws LoanException
     */
    public function invoiceFromAdvance(User $by, MicroLoan $file, int $advanceId, array $form, ?UploadedFile $scan = null): MicroPurchase
    {
        $this->assertApproved($file);
        $advance = $this->advanceOf($file, $advanceId);
        [$date, $amount, $problems] = $this->advanceAmount($advance, $form);
        $problems = [...$problems, ...$this->invoiceProblems($form), ...$this->grantLineProblems($file)];

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        /** @var CarbonImmutable $date */
        /** @var numeric-string $amount */
        $rate = Money::normalize($advance->rate, 6);
        $usd = $this->toUsd($amount, $advance->currency, $rate);
        $loanAmount = $this->inLoanCurrency($file, $amount, $advance->currency, $usd, $rate);
        $tags = $this->tags($file);
        $project = $this->project($file);

        return DB::transaction(function () use ($by, $file, $advance, $form, $scan, $date, $amount, $rate, $usd, $loanAmount, $tags, $project): MicroPurchase {
            $voucher = $this->post(new VoucherDraft(
                type: VoucherType::Journal, date: $date, preparedBy: (int) $by->getAuthIdentifier(),
                description: __('micro.voucher.invoice', ['beneficiary' => $file->beneficiary, 'merchant' => trim((string) ($form['merchant'] ?? '')), 'number' => trim((string) ($form['number'] ?? ''))]),
                lines: [
                    $this->loanLine($file, $loanAmount, $rate, $usd, $tags, $project),
                    new LineDraft(
                        ...$tags,
                        accountCode: self::ADVANCES_ACCOUNT, currency: $advance->currency, credit: $amount, rate: $advance->currency === 'USD' ? '1.000000' : $rate, creditUsd: $usd,
                        fundSource: $this->fundSource($file), contact: $advance->person, creditDonor: $this->donorAmount($usd, $project),
                    ),
                ],
            ), $file);
            app(VoucherDocuments::class)->record($voucher, 'invoice', (string) ($form['number'] ?? ''), (string) ($form['description_en'] ?? ''));

            return $this->record($by, $file, $voucher, MicroPurchase::INVOICE, $date, $advance->currency, $amount, $rate, $loanAmount, $usd, $form, $scan, ['advance_id' => $advance->id, 'person' => $advance->person]);
        });
    }

    /**
     * Gives back to the cash what is left of an advance.
     *
     * @param  array{date?: string, amount?: string}  $form
     *
     * @throws LoanException
     */
    public function returnAdvance(User $by, MicroLoan $file, int $advanceId, array $form): MicroPurchase
    {
        $this->assertApproved($file);
        $advance = $this->advanceOf($file, $advanceId);
        [$date, $amount, $problems] = $this->advanceAmount($advance, $form);

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        /** @var CarbonImmutable $date */
        /** @var numeric-string $amount */
        $rate = Money::normalize($advance->rate, 6);
        $lineRate = $advance->currency === 'USD' ? '1.000000' : $rate;
        $usd = $this->toUsd($amount, $advance->currency, $rate);
        $tags = $this->tags($file);
        $project = $this->project($file);

        return DB::transaction(function () use ($by, $file, $advance, $date, $amount, $rate, $lineRate, $usd, $tags, $project): MicroPurchase {
            $voucher = $this->post(new VoucherDraft(
                type: VoucherType::Receipt, date: $date, preparedBy: (int) $by->getAuthIdentifier(),
                description: __('micro.voucher.return', ['beneficiary' => $file->beneficiary, 'person' => (string) $advance->person]),
                lines: [
                    new LineDraft(
                        ...$tags,
                        accountCode: (string) $advance->cash_account, currency: $advance->currency, debit: $amount, rate: $lineRate, debitUsd: $usd,
                        fundSource: $this->fundSource($file), debitDonor: $this->donorAmount($usd, $project),
                    ),
                    new LineDraft(
                        ...$tags,
                        accountCode: self::ADVANCES_ACCOUNT, currency: $advance->currency, credit: $amount, rate: $lineRate, creditUsd: $usd,
                        fundSource: $this->fundSource($file), contact: $advance->person, creditDonor: $this->donorAmount($usd, $project),
                    ),
                ],
            ), $file);

            return $this->record($by, $file, $voucher, MicroPurchase::RETURN, $date, $advance->currency, $amount, $rate, '0.00', $usd, [], null, ['advance_id' => $advance->id, 'person' => $advance->person, 'cash_account' => $advance->cash_account]);
        });
    }

    /**
     * What is still open on an advance: given, less its invoices and what came back.
     *
     * @return numeric-string
     */
    public function openOn(MicroPurchase $advance): string
    {
        $used = MicroPurchase::query()->where('advance_id', $advance->id)->pluck('amount')->all();

        return array_reduce($used, fn (string $left, mixed $amount): string => bcsub($left, Money::normalize((string) $amount), 2), Money::normalize($advance->amount));
    }

    /**
     * The file's purchases so far, in the loan's currency and in dollars, against what was approved.
     *
     * @return array{invoiced: string, invoiced_usd: string, approved: string, difference: string, share: string|null, open_advances: string}
     */
    public function totals(MicroLoan $file): array
    {
        $invoiced = '0.00';
        $usd = '0.00';
        $open = '0.00';

        foreach ($file->purchases()->get() as $purchase) {
            if ($purchase->kind === MicroPurchase::INVOICE) {
                $invoiced = bcadd($invoiced, Money::normalize($purchase->loan_amount), 2);
                $usd = bcadd($usd, Money::normalize($purchase->usd), 2);
            } elseif ($purchase->kind === MicroPurchase::ADVANCE) {
                $open = bcadd($open, $this->toUsd($this->openOn($purchase), $purchase->currency, Money::normalize($purchase->rate, 6)), 2);
            }
        }

        $approved = bcadd(bcadd(Money::normalize($file->requested_amount ?? '0'), Money::normalize($file->seizure_fee), 2), Money::normalize($file->extra_amount), 2);
        $difference = bcsub($invoiced, $approved, 2);
        $base = Money::normalize($file->requested_amount ?? '0');

        return [
            'invoiced' => $invoiced, 'invoiced_usd' => $usd, 'approved' => $approved, 'difference' => $difference,
            'share' => bccomp($base, '0', 2) > 0 ? Money::normalize(bcdiv(bcmul($difference, '100', 6), $base, 6)) : null,
            'open_advances' => $open,
        ];
    }

    /**
     * Records the extra amount the project manager asks for (up to 10 % of the approved amount; more must be explained
     * by email to the Program Manager and the Financial Manager).
     *
     * @throws LoanException
     */
    public function setExtra(MicroLoan $file, string $amount, string $reason): MicroLoan
    {
        $this->assertApproved($file);
        $clean = str_replace([',', ' '], '', trim($amount));
        $value = $clean === '' ? '0.00' : (preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null);

        if ($value === null) {
            throw new LoanException([__('micro.problem.extra')]);
        }

        if (bccomp($value, '0', 2) > 0 && trim($reason) === '') {
            throw new LoanException([__('micro.problem.extra_reason')]);
        }

        $file->update(['extra_amount' => $value, 'extra_reason' => bccomp($value, '0', 2) > 0 ? mb_substr(trim($reason), 0, 255) : null]);

        return $file->refresh();
    }

    /** Whether the extra amount is above the share the project manager may ask for alone. */
    public function extraNeedsEmail(MicroLoan $file): bool
    {
        return bccomp(Money::normalize($file->extra_amount), bcmul(Money::normalize($file->requested_amount ?? '0'), self::EXTRA_SHARE, 2), 2) > 0;
    }

    /**
     * Takes back a movement whose voucher still waits for approval: its voucher is deleted with the reason. An advance
     * that has invoices or a return goes only after them.
     *
     * @throws LoanException
     */
    public function remove(User $by, MicroLoan $file, int $purchaseId, string $reason): void
    {
        $this->assertApproved($file);
        $purchase = $file->purchases()->whereKey($purchaseId)->firstOrFail();
        $voucher = $purchase->voucher;

        if ($purchase->kind === MicroPurchase::ADVANCE && MicroPurchase::query()->where('advance_id', $purchase->id)->exists()) {
            throw new LoanException([__('micro.problem.advance_used')]);
        }

        if ($voucher !== null && ! $voucher->trashed() && $voucher->status !== VoucherStatus::PendingApproval) {
            throw new LoanException([__('micro.problem.remove_approved', ['voucher' => $voucher->id])]);
        }

        DB::transaction(function () use ($by, $purchase, $voucher, $reason): void {
            if ($voucher !== null && ! $voucher->trashed()) {
                try {
                    app(PendingVoucherChanges::class)->delete($voucher, $by, $reason);
                } catch (LedgerRuleException $refused) {
                    throw new LoanException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
                }
            }

            $this->forget($purchase);
        });
    }

    /**
     * Closes the purchases when the beneficiary signs the receipt protocol: the loan opens at the recovery share of the
     * invoices' total, moved to 1204 (see the class's notes); the instalments start two months after the signature by
     * default and the trust bond must match the total.
     *
     * @param  array{signed_on?: string, trust_bond?: string, first_due?: string}  $form
     *
     * @throws LoanException
     */
    public function close(User $by, MicroLoan $file, array $form): MicroLoan
    {
        $this->assertApproved($file);
        $problems = [];
        $signed = $this->day((string) ($form['signed_on'] ?? ''));
        $firstDue = trim((string) ($form['first_due'] ?? '')) === '' ? $signed?->addMonthsNoOverflow(self::FIRST_DUE_MONTHS) : $this->day((string) $form['first_due']);
        $totals = $this->totals($file);
        $total = $totals['invoiced'];
        $bondText = trim((string) ($form['trust_bond'] ?? ''));
        $bond = $bondText === '' ? $total : $this->amount($bondText);
        $invoices = $file->purchases()->where('kind', MicroPurchase::INVOICE)->get();

        if ($signed === null || $firstDue === null) {
            $problems[] = __('loans.problem.date');
        }

        if ($invoices->isEmpty()) {
            $problems[] = __('micro.problem.no_invoices');
        }

        if (bccomp($totals['open_advances'], '0', 2) !== 0) {
            $problems[] = __('micro.problem.open_advance');
        }

        if ($bond === null || bccomp($bond, $total, 2) !== 0) {
            $problems[] = __('micro.problem.bond', ['total' => number_format((float) $total, 2), 'currency' => $file->currency]);
        }

        $instalment = Money::normalize($file->instalment_amount ?? '0');
        $loan = Money::normalize(bcdiv(bcmul($total, Money::normalize($file->recovery_percent), 6), '100', 6));
        $grant = bcsub($total, $loan, 2);
        $count = bccomp($instalment, '0', 2) > 0 ? (int) ceil((float) bcdiv($loan, $instalment, 6)) : 0;

        if (bccomp($loan, '0', 2) > 0 && ($count < 1 || $count > self::MAX_INSTALMENTS)) {
            $problems[] = __('micro.problem.instalments', ['max' => self::MAX_INSTALMENTS]);
        }

        $project = $this->project($file);
        $grantLine = $project !== null ? $this->grantLine($project) : null;

        /** @var CarbonImmutable $signed */
        /** @var CarbonImmutable $firstDue */
        $totalUsd = $totals['invoiced_usd'];
        $loanUsd = bccomp($total, '0', 2) > 0 ? Money::normalize(bcdiv(bcmul($totalUsd, $loan, 6), $total, 6)) : '0.00';
        $bookRate = $file->currency === 'USD' ? '1.000000' : Money::normalize(bcdiv($total, $totalUsd, 6), 6);

        // What the invoices already put on the loans: all of it for invoices booked before 2026-11-25, nothing for those
        // since, which went in full to the grants. The closing moves the difference so that the loans hold the
        // repayable share and the grants the rest.
        $onLoans = $this->onLoans($invoices);
        $move = bcsub($loan, $onLoans['amount'], 2);
        $moveUsd = bcsub($loanUsd, $onLoans['usd'], 2);

        if (bccomp($move, '0', 2) < 0 && $project !== null && $grantLine === null) {
            $problems[] = __('loans.problem.no_grant_line', ['project' => $project->code, 'account' => MicroLoans::GRANTS_ACCOUNT]);
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        $lastInvoice = $invoices->max(fn (MicroPurchase $purchase): string => $purchase->purchase_date->toDateString());
        $tags = $this->tags($file);

        return DB::transaction(function () use ($by, $file, $signed, $firstDue, $total, $loan, $grant, $loanUsd, $move, $moveUsd, $bookRate, $lastInvoice, $tags, $project, $grantLine, $instalment, $count): MicroLoan {
            $voucher = null;
            $percent = rtrim(rtrim(Money::normalize($file->recovery_percent), '0'), '.');

            if (bccomp($move, '0', 2) > 0) {
                // The repayable share leaves the grants for the loans. The grants' credit carries no budget line: the
                // donor's report keeps every invoice spent in full.
                $voucher = $this->post(new VoucherDraft(
                    type: VoucherType::Journal, date: $signed, preparedBy: (int) $by->getAuthIdentifier(),
                    description: __('micro.voucher.loan', ['beneficiary' => $file->beneficiary, 'percent' => $percent]),
                    lines: [
                        new LineDraft(
                            ...$tags,
                            accountCode: MicroLoans::LOANS_ACCOUNT, currency: $file->currency, debit: $move, rate: $bookRate, debitUsd: $moveUsd,
                            fundSource: $this->fundSource($file), contact: $file->beneficiary, debitDonor: $this->donorAmount($moveUsd, $project),
                        ),
                        new LineDraft(
                            ...$tags,
                            accountCode: MicroLoans::GRANTS_ACCOUNT, currency: $file->currency, credit: $move, rate: $bookRate, creditUsd: $moveUsd,
                            contact: $file->beneficiary, creditDonor: $this->donorAmount($moveUsd, $project),
                        ),
                    ],
                ), $file);
            } elseif (bccomp($move, '0', 2) < 0) {
                // Invoices booked on the loans: the grant share moves to the grants, on the budget line.
                $back = bcmul($move, '-1', 2);
                $backUsd = bcmul($moveUsd, '-1', 2);
                $voucher = $this->post(new VoucherDraft(
                    type: VoucherType::Journal, date: $signed, preparedBy: (int) $by->getAuthIdentifier(),
                    description: __('micro.voucher.grant', ['beneficiary' => $file->beneficiary, 'percent' => $percent]),
                    lines: [
                        new LineDraft(
                            ...$tags,
                            accountCode: $grantLine?->account_code ?? MicroLoans::GRANTS_ACCOUNT, currency: $file->currency, debit: $back, rate: $bookRate, debitUsd: $backUsd,
                            budgetLineKey: $grantLine?->key, contact: $file->beneficiary, debitDonor: $this->donorAmount($backUsd, $project),
                        ),
                        new LineDraft(
                            ...$tags,
                            accountCode: MicroLoans::LOANS_ACCOUNT, currency: $file->currency, credit: $back, rate: $bookRate, creditUsd: $backUsd,
                            fundSource: $this->fundSource($file), contact: $file->beneficiary, creditDonor: $this->donorAmount($backUsd, $project),
                        ),
                    ],
                ), $file);
            }

            $file->instalments()->delete();
            $given = '0.00';
            for ($i = 0; $i < $count; $i++) {
                $amount = $i === $count - 1 ? bcsub($loan, $given, 2) : $instalment;
                $given = bcadd($given, $amount, 2);
                $file->instalments()->create(['due_date' => $firstDue->addMonthsNoOverflow($i * $file->payment_interval_months)->toDateString(), 'amount' => $amount]);
            }

            $file->update([
                'stage' => MicroLoan::ACTIVE, 'loan_amount' => $loan, 'grant_amount' => $grant, 'trust_bond_amount' => $total,
                'protocol_signed_on' => $signed->toDateString(), 'loan_date' => $lastInvoice, 'first_payment_date' => $firstDue->toDateString(),
                'book_usd' => $loanUsd, 'closing_voucher_id' => $voucher?->id, 'fund_source' => $this->fundSource($file)->value,
            ]);

            return $file->refresh();
        });
    }

    /**
     * A voucher was deleted or rejected: its purchase goes, and a rejected closing opens the purchases again.
     */
    public function forgetVoucher(Voucher $voucher): void
    {
        foreach (MicroPurchase::query()->where('voucher_id', $voucher->id)->get() as $purchase) {
            $this->forget($purchase);
        }

        foreach (MicroLoan::query()->where('closing_voucher_id', $voucher->id)->get() as $file) {
            if (! $file->payments()->exists()) {
                MicroInstalment::query()->where('loan_id', $file->id)->delete();
                $file->update([
                    'stage' => MicroLoan::APPROVED, 'loan_amount' => '0.00', 'grant_amount' => '0.00', 'protocol_signed_on' => null, 'loan_date' => null,
                    'first_payment_date' => null, 'book_usd' => null, 'closing_voucher_id' => null,
                ]);
            }
        }
    }

    /**
     * The tags a file's lines carry: its project's, or the funder and city of its instalment fund.
     *
     * @return array{projectCode: string|null, donor: string|null, restriction: string|null, donorCurrency: string|null, city: string|null}
     */
    public function tags(MicroLoan $file): array
    {
        $project = $this->project($file);

        if ($project !== null) {
            return ['projectCode' => $project->code, 'donor' => $project->donor, 'restriction' => $project->restriction, 'donorCurrency' => $project->donor_currency, 'city' => null];
        }

        return ['projectCode' => null, 'donor' => $file->funder, 'restriction' => NetAssets::RESTRICTED, 'donorCurrency' => null, 'city' => $file->city];
    }

    public function fundSource(MicroLoan $file): FundSource
    {
        return $file->project_code === null ? FundSource::RecoveredLoans : FundSource::DonorGrant;
    }

    /**
     * The date, cash account, amount and rate of a payment or an advance, and what is wrong with them.
     *
     * @param  array{date?: string, cash_account?: string, amount?: string, rate?: string}  $form
     * @param  list<string>  $currencies
     * @return array{0: CarbonImmutable|null, 1: GlAccount|null, 2: string|null, 3: string|null, 4: list<string>}
     */
    private function cashMovement(MicroLoan $file, array $form, array $currencies): array
    {
        $problems = [];
        $date = $this->day((string) ($form['date'] ?? ''));
        $cash = GlAccount::query()->find((string) ($form['cash_account'] ?? ''));
        $amount = $this->amount((string) ($form['amount'] ?? ''));
        $rate = null;

        if ($date === null) {
            $problems[] = __('loans.problem.date');
        }

        if ($cash === null || ! $cash->is_cash || ! in_array((string) $cash->currency, $currencies, true) || preg_match((string) config('ledger.in_transit_pattern'), $cash->code) === 1) {
            $problems[] = __('loans.problem.cash_account', ['currency' => implode(' / ', $currencies)]);
            $cash = null;
        }

        if ($amount === null || bccomp($amount, '0', 2) <= 0) {
            $problems[] = __('micro.problem.amount');
        }

        // Dollars for a dollar loan need no rate. Whenever pounds come in, paid or lent, the pounds' rate is the one
        // typed or the month's: a pound loan paid in dollars takes it to count the invoice in the loan's pounds.
        if ($cash !== null && $cash->currency === 'USD' && $file->currency === 'USD') {
            $rate = '1.000000';
        } elseif ($cash !== null && $date !== null) {
            $pounds = $cash->currency === 'USD' ? $file->currency : (string) $cash->currency;
            $typed = str_replace([',', ' '], '', trim((string) ($form['rate'] ?? '')));
            $rate = $typed !== '' ? (preg_match('/^\d+(\.\d+)?$/', $typed) === 1 && bccomp($typed, '0', 6) > 0 ? Money::normalize($typed, 6) : null) : ($this->monthlyRates->forDay($pounds, $date)['rate'] ?? null);
            if ($rate === null) {
                $problems[] = __('loans.problem.rate');
            }
        }

        if ($file->project_code === null && $cash !== null && $date !== null && $amount !== null) {
            if ((string) $cash->city !== (string) $file->city) {
                $problems[] = __('loans.problem.fund_city', ['city' => (string) $file->city]);
            } else {
                $available = $this->funds->available((string) $file->funder, (string) $file->city, (string) $cash->currency, $date);
                if (bccomp($amount, $available, 2) > 0) {
                    $problems[] = __('loans.problem.fund_short', ['available' => $available, 'currency' => (string) $cash->currency]);
                }
            }
        }

        return [$date, $cash, $amount, $rate, $problems];
    }

    /**
     * @param  array{date?: string, amount?: string}  $form
     * @return array{0: CarbonImmutable|null, 1: string|null, 2: list<string>}
     */
    private function advanceAmount(MicroPurchase $advance, array $form): array
    {
        $problems = [];
        $date = $this->day((string) ($form['date'] ?? ''));
        $amount = $this->amount((string) ($form['amount'] ?? ''));
        $open = $this->openOn($advance);

        if ($date === null) {
            $problems[] = __('loans.problem.date');
        }

        if ($amount === null || bccomp($amount, '0', 2) <= 0) {
            $problems[] = __('micro.problem.amount');
        } elseif (bccomp($amount, $open, 2) > 0) {
            $problems[] = __('micro.problem.advance_short', ['open' => number_format((float) $open, 2), 'currency' => $advance->currency]);
        }

        return [$date, $amount, $problems];
    }

    /**
     * @param  array{merchant?: string, number?: string, description_ar?: string}  $form
     * @return list<string>
     */
    private function invoiceProblems(array $form): array
    {
        return trim((string) ($form['merchant'] ?? '')) === '' || trim((string) ($form['number'] ?? '')) === '' || trim((string) ($form['description_ar'] ?? '')) === ''
            ? [__('micro.problem.invoice')]
            : [];
    }

    private function advanceOf(MicroLoan $file, int $advanceId): MicroPurchase
    {
        return $file->purchases()->where('kind', MicroPurchase::ADVANCE)->whereKey($advanceId)->firstOrFail();
    }

    /**
     * The debit of a purchase, in the loan's currency (finance decisions of 2026-11-25): every invoice is spent in full on
     * the grants (5413), and the repayable share moves to the loans (1204) when the protocol is signed. A project's
     * invoice carries the project's grants budget line, so the donor's report lists it in full; an instalment fund's
     * file has no budget, so its invoice carries the fund's funder and city only.
     *
     * @param  array{projectCode: string|null, donor: string|null, restriction: string|null, donorCurrency: string|null, city: string|null}  $tags
     */
    private function loanLine(MicroLoan $file, string $loanAmount, string $rate, string $usd, array $tags, ?GlProject $project): LineDraft
    {
        $grantLine = $project !== null ? $this->grantLine($project) : null;

        return new LineDraft(
            ...$tags,
            accountCode: MicroLoans::GRANTS_ACCOUNT, currency: $file->currency, debit: $loanAmount, rate: $file->currency === 'USD' ? '1.000000' : $rate, debitUsd: $usd,
            budgetLineKey: $grantLine?->key, contact: $file->beneficiary, debitDonor: $this->donorAmount($usd, $project),
        );
    }

    /**
     * What an amount paid in a currency adds to the loan, in the loan's currency (finance decision of 2026-11-28: the
     * loan is fixed in its agreed currency, and paid in either): as paid in the same currency; a dollar loan paid in
     * pounds at their dollars; a pound loan paid in dollars at the pounds' rate, the month's or the one typed.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $usd
     * @param  numeric-string  $rate  the pounds' rate
     * @return numeric-string
     */
    private function inLoanCurrency(MicroLoan $file, string $amount, string $currency, string $usd, string $rate): string
    {
        if ($file->currency === $currency) {
            return Money::normalize($amount);
        }

        return $file->currency === 'USD' ? $usd : Money::normalize(bcmul($usd, $rate, 6));
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $extra
     */
    private function record(User $by, MicroLoan $file, Voucher $voucher, string $kind, CarbonImmutable $date, string $currency, string $amount, string $rate, string $loanAmount, string $usd, array $form, ?UploadedFile $scan, array $extra): MicroPurchase
    {
        $stored = $scan?->storeAs('micro-invoices/'.$file->id, Str::uuid()->toString().'.'.($scan->guessExtension() ?: 'bin'), 'local');
        $text = fn (string $key, int $max = 255): ?string => ($value = trim((string) ($form[$key] ?? ''))) === '' ? null : mb_substr($value, 0, $max);

        return $file->purchases()->create($extra + [
            'kind' => $kind, 'purchase_date' => $date->toDateString(), 'currency' => $currency, 'amount' => $amount, 'rate' => $rate,
            'loan_amount' => $loanAmount, 'usd' => $usd, 'voucher_id' => $voucher->id, 'is_seizure' => (bool) ($form['is_seizure'] ?? false),
            'merchant' => $text('merchant'), 'number' => $text('number', 60), 'description_ar' => $text('description_ar'), 'description_en' => $text('description_en'),
            'original_name' => $scan !== null && $stored ? Str::limit(basename($scan->getClientOriginalName()), 250, '') : null,
            'stored_path' => $stored ?: null, 'created_by' => (int) $by->getAuthIdentifier(),
        ]);
    }

    private function forget(MicroPurchase $purchase): void
    {
        if ($purchase->stored_path !== null) {
            Storage::disk('local')->delete($purchase->stored_path);
        }

        $purchase->delete();
    }

    /** @throws LoanException */
    private function post(VoucherDraft $draft, MicroLoan $file): Voucher
    {
        try {
            return $this->posting->post($draft->forMicroLoan($file->id));
        } catch (LedgerRuleException $refused) {
            throw new LoanException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
        }
    }

    /** @throws LoanException */
    private function assertApproved(MicroLoan $file): void
    {
        if ($file->stage !== MicroLoan::APPROVED) {
            throw new LoanException([__('micro.problem.not_approved')]);
        }
    }

    /**
     * What the file's invoices debited to the loans (1204), in the loan's currency and in dollars.
     *
     * @param  iterable<MicroPurchase>  $invoices
     * @return array{amount: numeric-string, usd: numeric-string}
     */
    private function onLoans(iterable $invoices): array
    {
        $ids = collect($invoices)->pluck('voucher_id')->filter()->values()->all();
        $row = DB::table('gl_voucher_lines')->whereIn('voucher_id', $ids)->where('account_code', MicroLoans::LOANS_ACCOUNT)
            ->selectRaw('sum(debit_orig) - sum(credit_orig) as amount, sum(debit_usd) - sum(credit_usd) as usd')->first();

        return ['amount' => Money::normalize($row?->amount ?? '0'), 'usd' => Money::normalize($row?->usd ?? '0')];
    }

    /** The project's budget line on the micro-project grants (5413), the first by key when there are several. */
    private function grantLine(GlProject $project): ?GlBudgetLine
    {
        return GlBudgetLine::query()->where('project_code', $project->code)->where('account_code', MicroLoans::GRANTS_ACCOUNT)->orderBy('key')->first();
    }

    /**
     * A project's invoice needs the project's grants budget line to be spent on.
     *
     * @return list<string>
     */
    private function grantLineProblems(MicroLoan $file): array
    {
        $project = $this->project($file);

        return $project !== null && $this->grantLine($project) === null
            ? [__('loans.problem.no_grant_line', ['project' => $project->code, 'account' => MicroLoans::GRANTS_ACCOUNT])]
            : [];
    }

    private function project(MicroLoan $file): ?GlProject
    {
        return $file->project_code !== null ? GlProject::query()->find($file->project_code) : null;
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

        return $project->donor_currency === 'USD' || bccomp($donorRate, '0', 6) <= 0 ? $usd : Money::normalize(bcdiv($usd, $donorRate, 6));
    }

    /** @return numeric-string|null */
    private function amount(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean) : null;
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }
}
