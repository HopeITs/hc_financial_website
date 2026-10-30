<?php

namespace App\Livewire;

use App\Livewire\Concerns\DefersLoading;
use App\Models\GlProject;
use App\Services\Ledger\DonorReport;
use App\Services\Ledger\TableExport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The donor report (F-13): for one project, what the donor sent and how it was spent against each budget line, in the
 * donor's currency and in dollars, with the funding period; exported as XLSX or PDF in the page's language.
 *
 * @phpstan-import-type Report from DonorReport
 */
#[Layout('components.layouts.app')]
class DonorReportScreen extends Component
{
    use DefersLoading;

    #[Url(as: 'project', except: '')]
    public string $project = '';

    public function mount(): void
    {
        if ($this->project === '' || ! GlProject::query()->where('is_general_account', false)->whereKey($this->project)->exists()) {
            $this->project = (string) GlProject::query()->where('is_general_account', false)->orderBy('code')->value('code');
        }
    }

    public function export(string $format, DonorReport $reports, TableExport $export): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $report = $reports->of($this->project);
        abort_if($report === null, 404);

        $rtl = app()->getLocale() === 'ar';
        $title = __('ledger.donor_report.title', ['project' => $report['project']->code]);
        $lines = $this->summary($report);
        $sections = $this->sections($report);
        $name = 'donor-report-'.$report['project']->code;

        if ($format === 'pdf') {
            $content = $export->pdf($title, $lines, $sections, $rtl);

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $name.'.pdf', ['Content-Type' => 'application/pdf']);
        }

        $path = $export->xlsx($title, $lines, $sections, $rtl);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function render(DonorReport $reports): View
    {
        return view('livewire.donor-report', [
            'projects' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name']),
            'report' => $this->project !== '' ? $reports->of($this->project) : null,
        ]);
    }

    /**
     * The lines under the title: donor, funding period, real rate, received, spent and what remains.
     *
     * @param  Report  $report
     * @return list<string>
     */
    private function summary(array $report): array
    {
        $project = $report['project'];
        $currency = $project->donor_currency;

        return array_values(array_filter([
            $project->name.' · '.__('ledger.donor_report.donor').': '.($project->donor ?? '—'),
            $project->contract_number ? __('ledger.donor_report.contract').': '.$project->contract_number : null,
            __('ledger.donor_report.period').': '.($project->start_date?->toDateString() ?? '—').' – '.($project->end_date?->toDateString() ?? '—'),
            $report['rate'] !== null ? __('ledger.donor_report.rate', ['currency' => $currency]).': '.$report['rate'] : null,
            __('ledger.donor_report.received').': '.number_format((float) $report['received']['donor'], 2).' '.$currency.' ('.number_format((float) $report['received']['usd'], 2).' USD)',
            __('ledger.donor_report.balance').': '.number_format((float) $report['balance']['donor'], 2).' '.$currency.' ('.number_format((float) $report['balance']['usd'], 2).' USD)',
        ]));
    }

    /**
     * @param  Report  $report
     * @return list<array{0: string, 1: list<string>, 2: list<list<string|null>>, 3: bool}>
     */
    private function sections(array $report): array
    {
        $lines = array_map(fn (array $line) => [
            $line['name'], $line['account_code'], $line['currency'], $line['budget'], $line['actual'], $line['remaining'], $line['percent'].'%', $line['usd'],
        ], $report['lines']);
        foreach ($report['totals'] as $currency => $total) {
            $lines[] = [__('ledger.donor_report.total').' '.$currency, '', $currency, $total['budget'], $total['actual'], $total['remaining'], $total['percent'].'%', count($report['totals']) === 1 ? $report['spent_usd'] : null];
        }

        $receipts = array_map(fn (array $receipt) => [
            $receipt['date'], $receipt['voucher'], $receipt['description'], $receipt['donor'], $receipt['usd'], $receipt['eur'], $receipt['eur_rate'],
        ], $report['receipts']);
        $receipts[] = [__('ledger.donor_report.total'), '', '', $report['received']['donor'], $report['received']['usd'], null, null];

        return [
            [__('ledger.donor_report.lines'), [
                __('ledger.donor_report.line'), __('ledger.builder.account'), __('ledger.builder.currency'), __('ledger.donor_report.budget'),
                __('ledger.donor_report.spent'), __('ledger.donor_report.remaining'), __('ledger.donor_report.percent'), __('ledger.donor_report.spent_usd'),
            ], $lines, $report['totals'] !== []],
            [__('ledger.donor_report.receipts'), [
                __('ledger.builder.date'), __('ledger.donor_report.voucher'), __('ledger.builder.description'),
                __('ledger.donor_report.amount_donor', ['currency' => $report['project']->donor_currency]), 'USD', 'EUR', __('ledger.donor_report.eur_rate'),
            ], $receipts, true],
        ];
    }
}
