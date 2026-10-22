<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Models\GlBudgetLine;
use App\Services\Ledger\PostingRuleViolation;

/**
 * Rule 6: a budget line is used only with its own project and fills its account. The budget line's limit is NOT
 * checked (Financial Manager, round 2): exceeding it is only a warning in the builder and at approval.
 */
class BudgetLineMatchesProjectAndAccount implements PostingRule
{
    public function check(VoucherDraft $draft): void
    {
        $keys = array_values(array_unique(array_filter(array_map(fn ($l) => $l->string('budget_line_key'), $draft->lines))));
        $budgetLines = GlBudgetLine::query()->whereIn('key', $keys)->get()->keyBy('key');

        foreach ($draft->lines as $index => $line) {
            $key = $line->string('budget_line_key');

            if ($key === null) {
                continue;
            }

            $budgetLine = $budgetLines->get($key);

            if ($budgetLine === null) {
                throw PostingRuleViolation::of('budget_line_unknown', ['line' => $index + 1, 'key' => $key]);
            }

            if ($line->string('project_code') !== $budgetLine->project_code) {
                throw PostingRuleViolation::of('budget_line_project', ['line' => $index + 1, 'key' => $key, 'project' => $budgetLine->project_code]);
            }

            if ($line->string('account_code') !== $budgetLine->account_code) {
                throw PostingRuleViolation::of('budget_line_account', ['line' => $index + 1, 'key' => $key, 'account' => $budgetLine->account_code]);
            }
        }
    }
}
