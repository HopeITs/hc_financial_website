<?php

namespace App\Livewire;

use App\Contracts\ProjectBalanceSource;
use App\Models\GlProject;
use App\Services\Projects\ProjectAccess;
use App\Services\Projects\ProjectDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * All projects with city, program, donor, status and, for roles that may see money, balances in SYP and USD.
 * A project manager sees only the projects linked to them.
 */
#[Layout('components.layouts.app')]
class Projects extends Component
{
    use WithPagination;

    #[Url(as: 'city')]
    public ?int $cityId = null;

    #[Url(as: 'program')]
    public ?string $program = null;

    #[Url(as: 'donor')]
    public ?int $donorId = null;

    #[Url(as: 'status')]
    public ?string $status = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['cityId', 'program', 'donorId', 'status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(ProjectDirectory $directory, ProjectBalanceSource $balances, ProjectAccess $access): View
    {
        $user = auth()->user();
        $showMoney = $access->seesMoney($user);

        $projects = $directory->paginate([
            'city' => $this->cityId,
            'program' => $this->program,
            'donor' => $this->donorId,
            'status' => $this->status,
            'search' => $this->search,
        ], $access->limitedTo($user));

        return view('livewire.projects', [
            'projects' => $projects,
            'balances' => $showMoney ? $balances->balancesFor($projects->pluck('accountId')->values()->all()) : collect(),
            'showMoney' => $showMoney,
            'cities' => $directory->cities(),
            'donors' => $directory->donors(),
            'programs' => $directory->programOptions(),
            'canCreate' => Gate::allows('screen', ['projects', 'create']),
            'canEdit' => Gate::allows('screen', ['projects', 'edit']),
            'ledgerProjects' => Gate::allows('screen', ['projects', 'create']) ? GlProject::query()->withCount('budgetLines')->orderBy('code')->get() : collect(),
        ]);
    }
}
