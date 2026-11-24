<?php

namespace App\Services\Assistant\Tools;

use App\Models\MicroLoan;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Micro-project files: the beneficiary, the stage of the file and, once the loan is open, what is still owed.
 */
class FindMicroFiles extends Tool
{
    public function name(): string
    {
        return 'find_micro_files';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'microfiles') || $this->can($user, 'payments');
    }

    public function definition(): array
    {
        return [
            'description' => 'Micro-project files, newest first: beneficiary, project number, project or fund (funder and city), profession, stage (draft, submitted = payment order waiting for approval, returned, approved = purchases being made, active = loan open, cancelled), loan and grant amounts, currency, recovery percent, instalment amount, and what the beneficiary still owes.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Part of the beneficiary\'s name or the project number.'],
                    'stage' => ['type' => 'string', 'enum' => [MicroLoan::DRAFT, MicroLoan::SUBMITTED, MicroLoan::RETURNED, MicroLoan::APPROVED, MicroLoan::ACTIVE, MicroLoan::CANCELLED]],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        if (! Schema::hasColumn('gl_micro_loans', 'stage')) {
            return ['files' => []];
        }

        $term = $this->text($input, 'search', 80);
        $stage = $this->text($input, 'stage', 20);
        $limit = $this->projectLimit($user);

        $files = MicroLoan::query()->with('instalments')
            ->when($limit !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereIn('project_code', $limit ?? [])->orWhereNull('project_code')))
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('beneficiary', 'like', '%'.$term.'%')->orWhere('project_number', 'like', $term.'%')))
            ->orderByDesc('id')
            ->limit($this->maxRows())
            ->get();

        return ['files' => $files->map(fn (MicroLoan $file): array => [
            'beneficiary' => $file->beneficiary,
            'project_number' => $file->project_number,
            'project_or_fund' => $file->label(),
            'profession' => $file->profession,
            'stage' => $file->stage,
            'loan_amount' => $file->loan_amount,
            'grant_amount' => $file->grant_amount,
            'currency' => $file->currency,
            'recovery_percent' => $file->recovery_percent,
            'instalment_amount' => $file->instalment_amount,
            'still_owed' => $file->stage === MicroLoan::ACTIVE ? $file->owed() : null,
            'return_reason' => $file->return_reason,
        ])->values()->all()];
    }
}
