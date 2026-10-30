<?php

namespace App\Services\Ledger;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherLine;
use Carbon\CarbonImmutable;

/**
 * Reversing an approved voucher (F-12): a new pending voucher of the same type with the same lines on the opposite sides,
 * linked to the original by `reverses_id` and carrying the reason. The original is never edited. The reversal is posted and
 * approved like any voucher, so every posting rule still applies to it (an open month, the accounts, the tags).
 */
class ReverseVoucher
{
    public function __construct(private readonly PostVoucher $posting) {}

    /**
     * @param  int  $by  the legacy `user`.`ID` of the person reversing
     * @param  CarbonImmutable|null  $date  the date of the reversal; today when null
     *
     * @throws LedgerRuleException
     */
    public function reverse(Voucher $voucher, int $by, string $reason, ?CarbonImmutable $date = null): Voucher
    {
        if (trim($reason) === '') {
            throw new LedgerRuleException([new Violation(0, __('vouchers.reverse.reason_needed'))]);
        }

        if ($reason !== trim($reason)) {
            $reason = trim($reason);
        }

        $problem = $this->problem($voucher);
        if ($problem !== null) {
            throw new LedgerRuleException([new Violation(0, $problem)]);
        }

        $lines = $voucher->lines->map(fn (VoucherLine $line) => new LineDraft(
            accountCode: $line->account_code,
            currency: $line->currency,
            debit: $line->credit_orig,
            credit: $line->debit_orig,
            rate: $line->rate,
            debitUsd: $line->credit_usd,
            creditUsd: $line->debit_usd,
            projectCode: $line->project_code,
            budgetLineKey: $line->budget_line_key,
            fundSource: $line->fund_source,
            contact: $line->contact,
            center: $line->center,
            donor: $line->donor,
            city: $line->city,
            restriction: $line->restriction,
            reason: $line->reason,
            liraType: $line->lira_type,
            donorCurrency: $line->donor_currency,
            debitDonor: $line->credit_donor,
            creditDonor: $line->debit_donor,
            eurAmount: $line->eur_amount,
            eurRate: $line->eur_rate,
            commissionUsd: $line->commission_usd,
        ))->all();

        return $this->posting->post(new VoucherDraft(
            type: $voucher->type,
            date: $date ?? CarbonImmutable::today(),
            preparedBy: $by,
            lines: array_values($lines),
            description: __('vouchers.reverse.description', ['number' => $voucher->id]).($voucher->description ? ' · '.$voucher->description : ''),
            transferKind: $voucher->transfer_kind,
            reversesId: $voucher->id,
            reversalReason: $reason,
        ));
    }

    /** Why the voucher cannot be reversed, or null when it can. */
    public function problem(Voucher $voucher): ?string
    {
        if ($voucher->status !== VoucherStatus::Approved || $voucher->trashed()) {
            return __('vouchers.reverse.not_approved');
        }

        if ($voucher->reverses_id !== null) {
            return __('vouchers.reverse.is_reversal');
        }

        if (Voucher::query()->where('reverses_id', $voucher->id)->exists()) {
            return __('vouchers.reverse.already', ['number' => (string) Voucher::query()->where('reverses_id', $voucher->id)->value('id')]);
        }

        return null;
    }
}
