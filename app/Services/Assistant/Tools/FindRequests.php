<?php

namespace App\Services\Assistant\Tools;

use App\Models\LedgerRequest;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * The project managers' requests (monthly and extra), within the projects the person may see.
 */
class FindRequests extends Tool
{
    public function name(): string
    {
        return 'find_requests';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'requests');
    }

    public function definition(): array
    {
        return [
            'description' => 'Project requests, newest first: number, type (monthly or extra), project, month, category, amount, currency, description, status (draft, submitted, returned, approved, rejected, closed) and the reason when returned or rejected.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Part of the description, or the request number.'],
                    'status' => ['type' => 'string'],
                    'project' => ['type' => 'string', 'description' => 'Exact project code.'],
                    'month' => ['type' => 'string', 'description' => 'YYYY-MM.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        if (! Schema::hasTable('gl_requests')) {
            return ['requests' => []];
        }

        $term = $this->text($input, 'search', 80);
        $number = preg_replace('/\D/', '', $term);
        $status = $this->text($input, 'status', 20);
        $project = $this->text($input, 'project', 40);
        $month = $this->text($input, 'month', 7);
        $limit = $this->projectLimit($user);

        $requests = LedgerRequest::query()
            ->when($limit !== null, fn ($query) => $query->whereIn('project_code', $limit ?? []))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($project !== '', fn ($query) => $query->where('project_code', $project))
            ->when(preg_match('/^\d{4}-\d{2}$/', $month) === 1, fn ($query) => $query->whereDate('month', '>=', $month.'-01')->whereDate('month', '<=', $month.'-31'))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('description', 'like', '%'.$term.'%')
                ->when($number !== '', fn ($byId) => $byId->orWhere('id', (int) $number))))
            ->orderByDesc('id')
            ->limit($this->maxRows())
            ->get();

        return ['requests' => $requests->map(fn (LedgerRequest $request): array => [
            'number' => $request->id,
            'type' => $request->type,
            'project' => $request->project_code,
            'month' => $request->month?->format('Y-m'),
            'category' => $request->category,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'description' => $request->description,
            'status' => $request->status,
            'return_reason' => $request->return_reason,
            'reject_reason' => $request->reject_reason,
        ])->values()->all()];
    }
}
