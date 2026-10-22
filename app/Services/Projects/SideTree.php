<?php

namespace App\Services\Projects;

use App\Models\Legacy\TreeView;
use Illuminate\Support\Collection;

/**
 * The side tree of the home page: city, program, sub-project, active account, from the legacy `tree_view`.
 */
class SideTree
{
    /**
     * @return array<int, array<string, mixed>> Each city with its `programs`, their `subProjects` and the `accounts` of each.
     */
    public function cities(): array
    {
        return TreeView::query()
            ->whereNotNull('CityID')
            ->where(fn ($query) => $query->whereNull('IsClosed')->orWhere('IsClosed', 0))
            ->orderBy('CityName')->orderBy('ProjectName')->orderBy('SubprojectName')->orderBy('AccountName')
            ->get()
            ->groupBy('CityID')
            ->map(fn (Collection $rows): array => [
                'id' => (int) $rows->first()->CityID,
                'name' => (string) $rows->first()->CityName,
                'programs' => $rows->groupBy('ProjectName')->map(fn (Collection $programRows, string $program): array => [
                    'name' => $program,
                    'subProjects' => $programRows->groupBy('SubprojectName')->map(fn (Collection $subRows, string $sub): array => [
                        'name' => $sub,
                        // A bond joins an account several times; each account shows once.
                        'accounts' => array_values(array_map('strval', $subRows->pluck('AccountName')->filter()->unique()->all())),
                    ])->values(),
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
