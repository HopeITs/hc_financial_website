<?php

namespace App\Livewire;

use App\Livewire\Concerns\DefersLoading;
use App\Livewire\Concerns\HasReportCurrency;
use App\Services\Ledger\TrialBalance as TrialBalanceReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The trial balance as at a date: approved vouchers only, by class, with the Dr = Cr check and a CSV export. */
#[Layout('components.layouts.app')]
class TrialBalance extends Component
{
    use DefersLoading;
    use HasReportCurrency;

    #[Url]
    public string $asAt = '';

    public function export(TrialBalanceReport $report): StreamedResponse
    {
        $data = $this->data($report);
        $currency = $this->reportCurrency();
        $arabic = app()->getLocale() === 'ar';

        return response()->streamDownload(function () use ($data, $currency, $arabic) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");
            $out->fputcsv([__('ledger.tb.class'), __('ledger.tb.account'), __('ledger.tb.name'), __('ledger.tb.debit').' '.$currency, __('ledger.tb.credit').' '.$currency, __('ledger.tb.net').' '.$currency]);
            foreach ($data['classes'] as $class) {
                foreach ($class['rows'] as $row) {
                    $out->fputcsv([__('ledger.class.'.$class['class']), $row['code'], $arabic ? $row['name_ar'] : $row['name_en'], $row['debit'], $row['credit'], $row['net']]);
                }
            }
            $out->fputcsv(['', '', __('ledger.tb.total'), $data['debit'], $data['credit'], $data['net']]);
        }, 'trial-balance'.($this->asAtDate() !== null ? '-'.$this->asAtDate() : '').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(TrialBalanceReport $report): View
    {
        return view('livewire.trial-balance', ['data' => $this->data($report), 'unit' => $this->reportCurrency()]);
    }

    /** @return array{classes: list<array{class: string, rows: list<array{code: string, name_en: string, name_ar: string, debit: string, credit: string, net: string}>, debit: string, credit: string, net: string}>, debit: string, credit: string, net: string, balanced: bool, difference: string} */
    private function data(TrialBalanceReport $report): array
    {
        return $report->asAt($this->asAtDate(), $this->reportCurrency(), $this->reportRate());
    }

    private function asAtDate(): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->asAt) === 1 ? $this->asAt : null;
    }
}
