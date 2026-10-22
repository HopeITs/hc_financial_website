<?php

namespace App\Services\Ledger\Rules;

use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;

/** Rule 6: a budget line is only used with its own project, and the line posts to the account the budget line fills. */
final class BudgetLineMatchesProjectAndAccount implements PostingRule
{
    public function number(): int
    {
        return 6;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        $violations = [];

        foreach ($draft->lines as $index => $line) {
            if ($line->budgetLineKey === null || $line->budgetLineKey === '') {
                continue;
            }

            $budgetLine = $context->budgetLine($line->budgetLineKey);

            if ($budgetLine === null) {
                $violations[] = new Violation($this->number(), __('ledger.rules.budget_line_unknown', ['key' => $line->budgetLineKey]), $index + 1);

                continue;
            }

            if ($line->projectCode !== $budgetLine->project_code) {
                $violations[] = new Violation($this->number(), __('ledger.rules.budget_line_project', [
                    'key' => $budgetLine->key, 'budget_project' => $budgetLine->project_code, 'project' => $line->projectCode ?? '-',
                ]), $index + 1);
            }

            if ($line->accountCode !== $budgetLine->account_code) {
                $violations[] = new Violation($this->number(), __('ledger.rules.budget_line_account', [
                    'key' => $budgetLine->key, 'budget_account' => $budgetLine->account_code, 'account' => $line->accountCode,
                ]), $index + 1);
            }
        }

        return $violations;
    }
}
