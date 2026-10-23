<?php

namespace App\Services\Ledger;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use App\Services\Ledger\Rules\BalancedInUsd;
use App\Services\Ledger\Rules\BudgetLineMatchesProjectAndAccount;
use App\Services\Ledger\Rules\CashAccountInOwnCurrency;
use App\Services\Ledger\Rules\DebitOrCreditNeverBoth;
use App\Services\Ledger\Rules\HawalaReceiptIsConsistent;
use App\Services\Ledger\Rules\MigratedVoucherKeepsLegacyId;
use App\Services\Ledger\Rules\MonthIsOpen;
use App\Services\Ledger\Rules\OnlyActivePostingAccounts;
use App\Services\Ledger\Rules\PostingRule;
use App\Services\Ledger\Rules\RequestRequiredAboveLimit;
use App\Services\Ledger\Rules\RequiredTagsFilled;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Posts a voucher: balances the cents (rule 4), checks every posting rule, numbers it (rule 10), saves it as
 * "pending approval" with its lines and writes the first audit row, all in one transaction. A broken rule
 * leaves no rows and uses no number.
 *
 * Rules 7, 9, 11 and 12 are about approving, amending, editing an account and closing a month; they live in
 * their own classes and are called by those actions.
 */
class PostVoucher
{
    public function __construct(
        private readonly VoucherNumberAllocator $numbers,
        private readonly RoundingLine $rounding,
    ) {}

    /** @throws LedgerRuleException */
    public function post(VoucherDraft $draft): Voucher
    {
        return DB::transaction(function () use ($draft) {
            $draft = $this->rounding->balance($draft);
            $violations = $this->violations($draft);

            if ($violations !== []) {
                throw new LedgerRuleException($violations);
            }

            $id = $this->numbers->next($draft->type, $draft->date->year);

            $voucher = Voucher::query()->create([
                'id' => $id,
                'type' => $draft->type,
                'transfer_kind' => $draft->transferKind,
                'request_id' => $draft->requestId,
                'date' => $draft->date->toDateString(),
                'description' => $draft->description,
                'status' => VoucherStatus::PendingApproval,
                'prepared_by' => $draft->preparedBy,
                'is_carry_forward' => $draft->isCarryForward,
                'is_temporary' => $draft->isTemporary,
                'source' => $draft->source,
                'legacy_action_id' => $draft->legacyActionId,
            ]);

            foreach ($draft->lines as $index => $line) {
                VoucherLine::query()->create($line->toRow($index + 1, $id));
            }

            VoucherAudit::query()->create(['voucher_id' => $id, 'action' => 'create', 'changed_by' => $draft->preparedBy]);

            return $voucher->load('lines');
        });
    }

    /**
     * Every posting rule a draft breaks, after the cents have been balanced: the voucher builder's live check list.
     *
     * @return list<Violation>
     */
    public function check(VoucherDraft $draft): array
    {
        return $this->violations($this->rounding->balance($draft));
    }

    /**
     * The posting rules again for a voucher that is already saved, as the approver sees it: accounts, currencies,
     * balance, tags, budget lines, the month and the request rule. Not the legacy id rule, which would find the
     * voucher itself, and no cents are moved: a saved voucher is already balanced.
     *
     * @return list<Violation>
     */
    public function recheck(Voucher $voucher): array
    {
        $draft = new VoucherDraft(
            type: $voucher->type,
            date: CarbonImmutable::parse($voucher->date->toDateString()),
            preparedBy: $voucher->prepared_by,
            lines: array_values($voucher->lines->map(fn (VoucherLine $line) => new LineDraft(
                accountCode: $line->account_code,
                currency: $line->currency,
                debit: $line->debit_orig,
                credit: $line->credit_orig,
                rate: $line->rate,
                debitUsd: $line->debit_usd,
                creditUsd: $line->credit_usd,
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
                debitDonor: $line->debit_donor,
                creditDonor: $line->credit_donor,
                eurAmount: $line->eur_amount,
                eurRate: $line->eur_rate,
                commissionUsd: $line->commission_usd,
            ))->all()),
            description: $voucher->description,
            source: $voucher->source,
            legacyActionId: $voucher->legacy_action_id,
            isCarryForward: $voucher->is_carry_forward,
            isTemporary: $voucher->is_temporary,
            requestId: $voucher->request_id,
            transferKind: $voucher->transfer_kind,
        );

        $context = LedgerContext::forDraft($draft);
        $violations = [];

        foreach ($this->rules() as $rule) {
            if (! $rule instanceof MigratedVoucherKeepsLegacyId) {
                array_push($violations, ...$rule->check($draft, $context));
            }
        }

        return $violations;
    }

    /** @return list<Violation> */
    private function violations(VoucherDraft $draft): array
    {
        $context = LedgerContext::forDraft($draft);
        $violations = [];

        foreach ($this->rules() as $rule) {
            array_push($violations, ...$rule->check($draft, $context));
        }

        return $violations;
    }

    /** @return list<PostingRule> */
    private function rules(): array
    {
        return [
            new OnlyActivePostingAccounts,
            new CashAccountInOwnCurrency,
            new DebitOrCreditNeverBoth,
            new BalancedInUsd,
            new RequiredTagsFilled,
            new BudgetLineMatchesProjectAndAccount,
            new MonthIsOpen,
            new MigratedVoucherKeepsLegacyId,
            new RequestRequiredAboveLimit,
            new HawalaReceiptIsConsistent,
        ];
    }
}
