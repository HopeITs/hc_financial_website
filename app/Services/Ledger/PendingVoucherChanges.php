<?php

namespace App\Services\Ledger;

use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\CashCount;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use App\Services\Assets\FixedAssets;
use App\Services\Entries\ProjectRevenues;
use App\Services\Ledger\Rules\MonthIsOpen;
use App\Services\Loans\MicroLoans;
use App\Services\Payroll\Payrolls;
use App\Services\Purchasing\PurchaseFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Changing or deleting a voucher that still waits for approval (finance decision of 2026-11-20): it keeps its number,
 * and the audit trail says who did it and, for a deletion, why. An approved voucher is never changed this way: it is
 * reversed (ReverseVoucher). Anyone who may prepare vouchers may do it, in an open month.
 */
class PendingVoucherChanges
{
    public function __construct(
        private readonly PostVoucher $posting,
        private readonly MonthIsOpen $monthIsOpen = new MonthIsOpen,
    ) {}

    /** What stops the person from changing or deleting the voucher, or null when nothing does. */
    public function problem(Voucher $voucher, User $user): ?string
    {
        if ($voucher->trashed() || $voucher->status !== VoucherStatus::PendingApproval) {
            return __('vouchers.pending_change.only_pending');
        }

        if (! $user->canOnScreen('vouchers', ScreenAction::Create)) {
            return __('vouchers.pending_change.not_allowed');
        }

        if ($voucher->type === VoucherType::Opening) {
            return __('vouchers.pending_change.not_opening');
        }

        $closed = $this->monthIsOpen->checkMonth($voucher->date->format('Y-m'));

        return $closed !== [] ? $closed[0]->message : null;
    }

    /**
     * The voucher's lines as rows of the voucher builder's grid, to change them there.
     *
     * @return list<array<string, string>>
     */
    public function rows(Voucher $voucher): array
    {
        return array_values($voucher->lines()->orderBy('line_no')->get()->map(fn (VoucherLine $line): array => [
            'account' => $line->account_code,
            'currency' => $line->currency,
            'side' => bccomp((string) $line->credit_orig, '0', 2) === 1 ? 'credit' : 'debit',
            'amount' => bccomp((string) $line->credit_orig, '0', 2) === 1 ? (string) $line->credit_orig : (string) $line->debit_orig,
            'rate' => $line->currency === Currencies::BASE ? '' : rtrim(rtrim((string) $line->rate, '0'), '.'),
            'project' => (string) $line->project_code,
            'budget_line' => (string) $line->budget_line_key,
            'fund_source' => (string) $line->fund_source?->value,
            'contact' => (string) $line->contact,
            'center' => (string) $line->center,
            'lira_type' => (string) $line->lira_type,
            'eur_amount' => $line->eur_amount !== null ? (string) $line->eur_amount : '',
            'eur_rate' => $line->eur_rate !== null ? rtrim(rtrim((string) $line->eur_rate, '0'), '.') : '',
            'commission' => bccomp((string) $line->commission_usd, '0', 2) === 1 ? (string) $line->commission_usd : '',
        ])->all());
    }

    /**
     * Replaces the voucher's header and lines with the draft's. The type and the year stay: they are in its number.
     *
     * @throws LedgerRuleException
     */
    public function update(Voucher $voucher, VoucherDraft $draft, User $by): Voucher
    {
        $this->guard($voucher, $by);

        if ($draft->type !== $voucher->type || $draft->date->year !== $voucher->date->year) {
            throw new LedgerRuleException([new Violation(0, __('vouchers.pending_change.same_type_and_year'))]);
        }

        return $this->posting->replace($voucher, $draft, (int) $by->getAuthIdentifier());
    }

    /**
     * Deletes the voucher (soft: its number is not given again) with the reason in its audit trail. A cash count it
     * adjusted can be adjusted again; a micro-loan repayment or write-off it made goes back to the instalments.
     *
     * @throws LedgerRuleException
     */
    public function delete(Voucher $voucher, User $by, string $reason): void
    {
        $this->guard($voucher, $by);

        if (trim($reason) === '') {
            throw new LedgerRuleException([new Violation(0, __('vouchers.pending_change.needs_reason'))]);
        }

        DB::transaction(function () use ($voucher, $by, $reason): void {
            VoucherAudit::query()->create(['voucher_id' => $voucher->id, 'action' => 'delete', 'changed_by' => (int) $by->getAuthIdentifier(), 'reason' => trim($reason)]);
            CashCount::query()->where('voucher_id', $voucher->id)->update(['voucher_id' => null]);
            app(MicroLoans::class)->forgetVoucher($voucher);
            if (Schema::hasTable('gl_purchases')) {
                app(PurchaseFiles::class)->forgetVoucher($voucher);
            }
            if (Schema::hasTable('gl_payrolls')) {
                app(Payrolls::class)->forgetVoucher($voucher);
            }
            if (Schema::hasTable('gl_revenue_sheets')) {
                app(ProjectRevenues::class)->forgetVoucher($voucher);
            }
            // A deleted write-off or sale puts the fixed asset back (finance request of 2026-11-28).
            if (Schema::hasTable('gl_assets')) {
                app(FixedAssets::class)->forgetVoucher($voucher);
            }
            $voucher->delete();
        });
    }

    /** @throws LedgerRuleException */
    private function guard(Voucher $voucher, User $user): void
    {
        $problem = $this->problem($voucher, $user);

        if ($problem !== null) {
            throw new LedgerRuleException([new Violation(0, $problem)]);
        }
    }
}
