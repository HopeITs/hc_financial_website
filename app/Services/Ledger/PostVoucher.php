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
use App\Services\Ledger\Rules\MigratedVoucherKeepsLegacyId;
use App\Services\Ledger\Rules\MonthIsOpen;
use App\Services\Ledger\Rules\OnlyActivePostingAccounts;
use App\Services\Ledger\Rules\PostingRule;
use App\Services\Ledger\Rules\RequiredTagsFilled;
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
        ];
    }
}
