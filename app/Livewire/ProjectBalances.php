<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasReportCurrency;
use App\Models\GlProject;
use App\Services\Ledger\ProjectBalances as ProjectBalancesReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Project balances from approved voucher lines, with budget against actual and where the cash sits for one project. */
#[Layout('components.layouts.app')]
class ProjectBalances extends Component
{
    use HasReportCurrency;

    #[Url]
    public string $project = '';

    public function render(ProjectBalancesReport $report): View
    {
        $projects = GlProject::query()->orderBy('code')->get();
        $selected = $projects->firstWhere('code', $this->project);

        return view('livewire.project-balances', [
            'data' => $report->report($this->reportCurrency(), $this->reportRate()),
            'projects' => $projects,
            'selected' => $selected,
            'budget' => $selected ? $report->budgetVsActual($selected->code) : [],
            'locations' => $report->fundLocations($selected?->code),
            'unit' => $this->reportCurrency(),
        ]);
    }
}
