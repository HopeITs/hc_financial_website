<?php

namespace App\Services\Assistant\Tools;

use App\Enums\AccountClass;
use App\Enums\ScreenAction;
use App\Models\GlAccount;
use App\Models\User;

/**
 * Accounts of the chart by code or name: what the model needs to name the right cash, income or expense account.
 */
class FindAccounts extends Tool
{
    public function name(): string
    {
        return 'find_accounts';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'chart') || $this->can($user, 'statement') || $this->can($user, 'single', ScreenAction::Create);
    }

    public function definition(): array
    {
        return [
            'description' => 'Search the chart of accounts by code or by name (English or Arabic). Returns active accounts with their class, level (header or posting), currency, city, whether they are cash accounts, and the usage note. Only posting accounts take entries; cash accounts are the safes, banks and the transfer company.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Part of the code or the name. Leave empty to list by the other filters.'],
                    'class' => ['type' => 'string', 'enum' => array_map(fn (AccountClass $class) => $class->value, AccountClass::cases())],
                    'cash_only' => ['type' => 'boolean', 'description' => 'Only cash accounts (safes, banks, transfer company).'],
                    'posting_only' => ['type' => 'boolean', 'description' => 'Only posting accounts, the ones that take entries.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $term = $this->text($input, 'query', 60);
        $class = AccountClass::tryFrom($this->text($input, 'class', 20));

        $accounts = GlAccount::query()
            ->where('is_active', true)
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('code', 'like', $term.'%')
                ->orWhere('name_en', 'like', '%'.$term.'%')
                ->orWhere('name_ar', 'like', '%'.$term.'%')))
            ->when($class !== null, fn ($query) => $query->where('class', $class->value))
            ->when($this->flag($input, 'cash_only'), fn ($query) => $query->where('is_cash', true))
            ->when($this->flag($input, 'posting_only'), fn ($query) => $query->where('level', 'posting'))
            ->orderBy('code')
            ->limit($this->maxRows() + 1)
            ->get();

        return [
            'accounts' => $accounts->take($this->maxRows())->map(fn (GlAccount $account) => [
                'code' => $account->code,
                'name_en' => $account->name_en,
                'name_ar' => $account->name_ar,
                'class' => $account->class->value,
                'level' => $account->level->value,
                'currency' => $account->currency,
                'city' => $account->city,
                'is_cash' => $account->is_cash,
                'required_tags' => $account->required_tags,
                'usage_note' => $account->usage_note,
            ])->values()->all(),
            'more' => $accounts->count() > $this->maxRows(),
        ];
    }
}
