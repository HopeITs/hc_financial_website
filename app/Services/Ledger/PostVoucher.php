<?php

namespace App\Services\Ledger;

use App\Data\LineDraft;
use App\Data\VoucherDraft;
use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Ledger\Rules\BalancedInUsd;
use App\Services\Ledger\Rules\BudgetLineMatchesProjectAndAccount;
use App\Services\Ledger\Rules\CashAccountInOwnCurrency;
use App\Services\Ledger\Rules\DebitOrCreditNeverBoth;
use App\Services\Ledger\Rules\MigratedVouchersKeepTheirLegacyId;
use App\Services\Ledger\Rules\NothingPostsIntoAClosedMonth;
use App\Services\Ledger\Rules\OnlyActivePostingAccounts;
use App\Services\Ledger\Rules\PostingRule;
use App\Services\Ledger\Rules\RequestRequiredAboveLimit;
use App\Services\Ledger\Rules\RequiredTagsFilled;
use Illuminate\Support\Facades\DB;

/**
 * Validates a voucher against the posting rules, numbers it and saves it as Pending approval with its lines and a
 * first audit row, all in one transaction: a failed rule leaves no rows and no consumed number.
 */
class PostVoucher
{
    /** @var list<PostingRule> */
    private readonly array $rules;

    public function __construct(
        private readonly VoucherNumbers $numbers,
        OnlyActivePostingAccounts $activeAccounts,
        CashAccountInOwnCurrency $cashCurrency,
        DebitOrCreditNeverBoth $debitOrCredit,
        BalancedInUsd $balanced,
        RequiredTagsFilled $requiredTags,
        BudgetLineMatchesProjectAndAccount $budgetLine,
        NothingPostsIntoAClosedMonth $closedMonth,
        MigratedVouchersKeepTheirLegacyId $legacyId,
        RequestRequiredAboveLimit $requestRequired,
    ) {
        $this->rules = [
            $activeAccounts, $cashCurrency, $debitOrCredit, $balanced, $requiredTags, $budgetLine, $closedMonth, $legacyId, $requestRequired,
        ];
    }

    /**
     * @throws PostingRuleViolation
     */
    public function handle(VoucherDraft $draft): Voucher
    {
        return DB::transaction(function () use ($draft) {
            foreach ($this->rules as $rule) {
                $rule->check($draft);
            }

            $voucher = Voucher::query()->create([
                'id' => $this->numbers->next($draft->type, (int) $draft->date->format('Y')),
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
                $voucher->lines()->create($this->lineAttributes($line, $index + 1));
            }

            VoucherAudit::query()->create([
                'voucher_id' => $voucher->id, 'action' => 'created', 'new_value' => VoucherStatus::PendingApproval->value,
                'changed_by' => $draft->preparedBy,
            ]);

            return $voucher->load('lines');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function lineAttributes(LineDraft $line, int $lineNo): array
    {
        $attributes = $line->toArray();
        unset($attributes['voucher_id'], $attributes['id']);

        return $attributes + ['line_no' => $lineNo];
    }
}
