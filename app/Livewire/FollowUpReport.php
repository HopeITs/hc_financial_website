<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasMonthFilter;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\FollowUpFilters;
use App\Services\Ledger\FollowUpReports;
use App\Services\Projects\ProjectAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One screen for the four follow-up reports (recon, uncomp, advances, loans), told apart by the route's screen slug.
 * They share one filter bar (dates, city, project) and a CSV export; a project manager sees only their projects.
 */
#[Layout('components.layouts.app')]
class FollowUpReport extends Component
{
    use HasMonthFilter;

    public string $screen = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $city = '';

    #[Url]
    public string $project = '';

    public function mount(string $screen): void
    {
        abort_unless(in_array($screen, FollowUpReports::REPORTS, true), 404);
        $this->screen = $screen;
    }

    public function export(FollowUpReports $reports): StreamedResponse
    {
        $data = $this->data($reports);

        return response()->streamDownload(function () use ($data) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");
            $out->fputcsv(array_map(fn (array $column) => __("ledger.follow.columns.{$column['key']}"), $data['columns']));
            foreach ($data['rows'] as $row) {
                $out->fputcsv(array_map(fn (array $column) => $row[$column['key']] ?? '', $data['columns']));
            }
            if ($data['totals'] !== []) {
                $out->fputcsv(array_map(fn (array $column, int $index) => $data['totals'][$column['key']] ?? ($index === 0 ? __('ledger.op.total') : ''), $data['columns'], array_keys($data['columns'])));
            }
        }, $this->screen.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(FollowUpReports $reports): View
    {
        $limit = $this->limitedCodes();

        return view('livewire.follow-up-report', [
            'data' => $this->data($reports),
            'projects' => GlProject::query()->when($limit !== null, fn ($query) => $query->whereIn('code', $limit ?? []))->orderBy('code')->get(['code', 'name']),
            'cities' => GlProject::query()->whereNotNull('city')->where('city', '!=', '')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    /** @return array{columns: list<array{key: string, type: string}>, rows: list<array<string, string|null>>, totals: array<string, string>} */
    private function data(FollowUpReports $reports): array
    {
        $date = fn (string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';

        return $reports->run($this->screen, FollowUpFilters::from([
            'from' => $date($this->from), 'to' => $date($this->to), 'city' => $this->city, 'project' => $this->project,
        ], $this->limitedCodes()));
    }

    /** @return list<string>|null Project codes a project manager is limited to, or null when not limited. */
    private function limitedCodes(): ?array
    {
        $user = Auth::user();
        $limit = $user instanceof User ? app(ProjectAccess::class)->limitedTo($user) : null;

        return $limit === null ? null : array_values(GlProject::query()->whereIn('legacy_account_id', $limit)->pluck('code')->all());
    }
}
