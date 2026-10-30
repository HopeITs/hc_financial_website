<?php

namespace App\Livewire;

use App\Livewire\Concerns\DefersLoading;
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
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One screen for the four follow-up reports (recon, uncomp, xreserve, advances, loans), told apart by the route's screen slug.
 * They share one filter bar (dates, city, project) and a CSV export; a project manager sees only their projects.
 */
#[Layout('components.layouts.app')]
class FollowUpReport extends Component
{
    use DefersLoading;
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

    /** The same rows as the CSV, as an Excel file (right to left in Arabic). */
    public function exportXlsx(FollowUpReports $reports): StreamedResponse
    {
        $data = $this->data($reports);
        $path = (string) tempnam(sys_get_temp_dir(), 'fur');
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr(__('navigation.screens.'.$this->screen), 0, 31));
        $writer->getCurrentSheet()->setSheetView((new SheetView)->withRightToLeft(app()->getLocale() === 'ar'));
        $bold = (new Style)->withFontBold(true);

        $writer->addRow(new Row(array_map(fn (array $column) => Cell::fromValue(__("ledger.follow.columns.{$column['key']}"), $bold), $data['columns'])));
        $cell = fn (array $column, mixed $value, ?Style $style = null) => Cell::fromValue($column['type'] === 'money' && is_numeric($value) ? (float) $value : (string) ($value ?? ''), $style);

        foreach ($data['rows'] as $row) {
            $writer->addRow(new Row(array_map(fn (array $column) => $cell($column, $row[$column['key']] ?? ''), $data['columns'])));
        }

        if ($data['totals'] !== []) {
            $writer->addRow(new Row(array_map(fn (array $column, int $index) => $cell($column, $data['totals'][$column['key']] ?? ($index === 0 ? __('ledger.op.total') : ''), $bold), $data['columns'], array_keys($data['columns']))));
        }

        $writer->close();

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $this->screen.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
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
