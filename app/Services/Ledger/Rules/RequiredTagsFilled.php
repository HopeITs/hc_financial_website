<?php

namespace App\Services\Ledger\Rules;

use App\Data\VoucherDraft;
use App\Models\GlAccount;
use App\Services\Ledger\PostingRuleViolation;

/** Rule 5: every tag an account requires (Project, Budget line, Fund source, Contact, Center) is filled on the line. */
class RequiredTagsFilled implements PostingRule
{
    /** Tag name on the account => column on the line. */
    private const COLUMNS = [
        'Project' => 'project_code',
        'Budget line' => 'budget_line_key',
        'Fund source' => 'fund_source',
        'Contact' => 'contact',
        'Center' => 'center',
    ];

    public function check(VoucherDraft $draft): void
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($l) => $l->string('account_code'), $draft->lines))));
        $accounts = GlAccount::query()->whereIn('code', $codes)->get()->keyBy('code');

        foreach ($draft->lines as $index => $line) {
            $account = $accounts->get($line->string('account_code') ?? '');

            foreach ($account->required_tags ?? [] as $tag) {
                $column = self::COLUMNS[$tag] ?? null;

                if ($column !== null && $line->string($column) === null) {
                    throw PostingRuleViolation::of('required_tag', ['line' => $index + 1, 'account' => $account->code, 'tag' => $tag]);
                }
            }
        }
    }
}
