<?php

namespace App\Services\Assistant\Tools;

use App\Models\GlProject;
use App\Models\User;
use App\Services\Assistant\ToolInputException;
use App\Services\Ledger\ProjectBalances;
use App\Services\Projects\ProjectAccess;

/**
 * A project's budget against what was spent, line by line, and where its funds are.
 */
class ProjectBudgetTool extends Tool
{
    public function __construct(private readonly ProjectBalances $balances) {}

    public function name(): string
    {
        return 'project_budget';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'projects') && $this->can($user, ProjectAccess::MONEY_SCREEN);
    }

    public function definition(): array
    {
        return [
            'description' => 'Budget against actual for every budget line of one project, from approved vouchers, in each line\'s own currency (budget, actual, remaining, percent used), and where the project\'s funds are by cash account (amount in the account\'s currency and its USD value).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'project' => ['type' => 'string', 'description' => 'Exact project code; use find_projects to find it.'],
                ],
                'required' => ['project'],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $code = $this->text($input, 'project', 40);

        if ($code === '' || ! GlProject::query()->whereKey($code)->exists()) {
            throw new ToolInputException('No project with that code; use find_projects.');
        }

        if (! $this->mayOpenProject($user, $code)) {
            throw new ToolInputException('This person may not see that project.');
        }

        return [
            'project' => $code,
            'basis' => 'approved vouchers only',
            'budget_lines' => $this->balances->budgetVsActual($code),
            'fund_locations' => $this->balances->fundLocations($code),
        ];
    }
}
