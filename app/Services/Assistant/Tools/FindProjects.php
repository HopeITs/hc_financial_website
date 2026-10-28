<?php

namespace App\Services\Assistant\Tools;

use App\Enums\ScreenAction;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;

/**
 * Ledger projects (and a project's budget lines), within the projects the person may see.
 */
class FindProjects extends Tool
{
    public function name(): string
    {
        return 'find_projects';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'projects') || $this->can($user, 'single', ScreenAction::Create);
    }

    public function definition(): array
    {
        return [
            'description' => 'Search ledger projects by code, name, city or donor. Give an exact project code with include_budget_lines to also get that project\'s budget lines (key, name, expense account, currency, budget). General accounts (GEN-<city>-USD/SYP) hold each city\'s treasury.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Part of the code, name, city or donor. Leave empty to list projects.'],
                    'include_budget_lines' => ['type' => 'boolean', 'description' => 'With an exact project code as query: also return its budget lines.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $term = $this->text($input, 'query', 60);
        $limit = $this->projectLimit($user);

        $projects = GlProject::query()
            ->when($limit !== null, fn ($query) => $query->whereIn('code', $limit ?? []))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('code', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')
                ->orWhere('city', 'like', '%'.$term.'%')
                ->orWhere('donor', 'like', '%'.$term.'%')))
            ->orderBy('code')
            ->limit($this->maxRows() + 1)
            ->get();

        $result = [
            'projects' => $projects->take($this->maxRows())->map(fn (GlProject $project) => [
                'code' => $project->code,
                'name' => $project->name,
                'city' => $project->city,
                'donor' => $project->donor,
                'donor_currency' => $project->donor_currency,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'is_general_account' => $project->is_general_account,
            ])->values()->all(),
            'more' => $projects->count() > $this->maxRows(),
        ];

        $exact = $projects->first(fn (GlProject $project) => strcasecmp($project->code, $term) === 0);

        if ($this->flag($input, 'include_budget_lines') && $exact !== null) {
            $result['budget_lines'] = GlBudgetLine::query()->where('project_code', $exact->code)->orderBy('key')->get()
                ->map(fn (GlBudgetLine $line) => [
                    'key' => $line->key, 'name' => $line->name, 'account_code' => $line->account_code,
                    'currency' => $line->currency, 'budget' => $line->budget, 'donor' => $line->donor,
                ])->values()->all();
        }

        return $result;
    }
}
