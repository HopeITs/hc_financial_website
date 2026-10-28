<?php

namespace App\Services\Assistant\Tools;

use App\Models\GlAccount;
use App\Models\User;
use App\Services\Assistant\ToolInputException;
use App\Services\Ledger\AccountStatement;

/**
 * The statement of one account for a period, as the statement screen shows it.
 */
class AccountStatementTool extends Tool
{
    public function __construct(private readonly AccountStatement $statements) {}

    public function name(): string
    {
        return 'account_statement';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'statement');
    }

    public function definition(): array
    {
        return [
            'description' => 'Statement of one account in USD from approved vouchers: opening balance, the lines of the period in date order with a running balance, the totals and the closing balance. Balances are signed debit minus credit. Long statements return only the latest lines (truncated is true); the totals always cover the whole period.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'account' => ['type' => 'string', 'description' => 'Account code; a header account covers every account below it.'],
                    'project' => ['type' => 'string', 'description' => 'Optional project code to keep only its lines.'],
                    'from' => ['type' => 'string', 'description' => 'First day, YYYY-MM-DD.'],
                    'to' => ['type' => 'string', 'description' => 'Last day, YYYY-MM-DD.'],
                ],
                'required' => ['account'],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $code = $this->text($input, 'account', 20);
        $project = $this->text($input, 'project', 40);

        if ($code === '' || ! GlAccount::query()->whereKey($code)->exists()) {
            throw new ToolInputException('No account with that code; use find_accounts.');
        }

        if ($project !== '' && ! $this->mayOpenProject($user, $project)) {
            throw new ToolInputException('This person may not see that project.');
        }

        $statement = $this->statements->for($code, $project !== '' ? $project : null, $this->day($input, 'from'), $this->day($input, 'to'));
        $rows = $statement['rows'];

        return [
            'account' => $code,
            'currency' => 'USD',
            'opening' => $statement['opening'],
            'debit' => $statement['debit'],
            'credit' => $statement['credit'],
            'closing' => $statement['closing'],
            'line_count' => count($rows),
            'truncated' => count($rows) > $this->maxRows(),
            'rows' => array_slice($rows, -$this->maxRows()),
        ];
    }
}
