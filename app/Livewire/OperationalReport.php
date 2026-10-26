<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasMonthFilter;
use App\Livewire\Concerns\HasReportCurrency;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Services\Ledger\OperationalReports;
use App\Services\Ledger\ReportFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One screen for the six operational reports (rexp, rcash, rbl, rrev, rfixed, rattach), told apart by the route's
 * screen slug. They share the Windows program's filters and the report currency switch, and export what is shown.
 */
#[Layout('components.layouts.app')]
class OperationalReport extends Component
{
    use HasMonthFilter;
    use HasReportCurrency;

    public string $screen = '';

    #[Url]
    public string $project = '';

    #[Url]
    public string $donor = '';

    #[Url]
    public string $account = '';

    #[Url(as: 'line_currency')]
    public string $lineCurrency = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(string $screen): void
    {
        abort_unless(in_array($screen, OperationalReports::REPORTS, true), 404);
        $this->screen = $screen;
    }

    public function export(OperationalReports $reports): StreamedResponse
    {
        $data = $this->data($reports);

        return response()->streamDownload(function () use ($data) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");
            $out->fputcsv(array_map(fn (array $column) => __("ledger.op.columns.{$column['key']}"), $data['columns']));
            foreach ($data['rows'] as $row) {
                $out->fputcsv(array_map(fn (array $column) => $row[$column['key']] ?? '', $data['columns']));
            }
            if ($data['totals'] !== []) {
                $out->fputcsv(array_map(fn (array $column, int $index) => $data['totals'][$column['key']] ?? ($index === 0 ? __('ledger.op.total') : ''), $data['columns'], array_keys($data['columns'])));
            }
        }, $this->screen.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(OperationalReports $reports): View
    {
        return view('livewire.operational-report', [
            'data' => $this->data($reports),
            'unit' => $this->reportCurrency(),
            'usesCurrency' => in_array($this->screen, ['rexp', 'rrev', 'rfixed'], true),
            'usesDates' => $this->screen !== 'rfixed',
            'projects' => GlProject::query()->orderBy('code')->get(['code', 'name']),
            'accounts' => GlAccount::query()->orderBy('code')->get(),
            'donors' => DB::table('gl_projects')->whereNotNull('donor')->where('donor', '!=', '')->distinct()->orderBy('donor')->pluck('donor'),
        ]);
    }

    /** @return array{columns: list<array{key: string, type: string}>, rows: list<array<string, string|null>>, totals: array<string, string>} */
    private function data(OperationalReports $reports): array
    {
        $date = fn (string $value): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;

        return $reports->run($this->screen, ReportFilters::from([
            'project' => $this->project, 'donor' => $this->donor, 'account' => $this->account,
            'currency' => $this->lineCurrency, 'from' => $date($this->from), 'to' => $date($this->to),
        ]), $this->reportCurrency(), $this->reportRate());
    }
}
