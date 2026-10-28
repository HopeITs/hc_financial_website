<?php

namespace App\Services\Assistant\Tools;

use App\Models\GlAccount;
use App\Models\User;
use App\Services\Assistant\ToolInputException;
use App\Services\Chart\AccountBalances;

/**
 * Balances of chart accounts in USD from approved vouchers, as the chart and the trial balance show them.
 */
class AccountBalancesTool extends Tool
{
    public function __construct(private readonly AccountBalances $balances) {}

    public function name(): string
    {
        return 'account_balances';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'tb') || $this->can($user, 'chart');
    }

    public function definition(): array
    {
        return [
            'description' => 'Current balances in USD of the given account codes, from approved vouchers only, on each account\'s normal side. A header account carries the sum of every account below it. For a balance in the account\'s own currency, or by project, use account_statement or treasury_position.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'codes' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 50, 'description' => 'Account codes, e.g. ["1111", "41"].'],
                ],
                'required' => ['codes'],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(fn ($code) => is_scalar($code) ? trim((string) $code) : '', is_array($input['codes'] ?? null) ? $input['codes'] : []),
        )));

        if ($codes === []) {
            throw new ToolInputException('Give at least one account code in codes.');
        }

        $all = $this->balances->all();
        $names = GlAccount::query()->whereIn('code', $codes)->get(['code', 'name_en', 'name_ar'])->keyBy('code');

        return [
            'currency' => 'USD',
            'basis' => 'approved vouchers only',
            'balances' => array_map(fn (string $code) => isset($names[$code]) ? [
                'code' => $code,
                'name_en' => $names[$code]->name_en,
                'name_ar' => $names[$code]->name_ar,
                'balance_usd' => $all[$code] ?? '0.00',
            ] : ['code' => $code, 'error' => 'no such account'], array_slice($codes, 0, 50)),
        ];
    }
}
