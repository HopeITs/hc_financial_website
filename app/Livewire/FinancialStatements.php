<?php

namespace App\Livewire;

use App\Livewire\Concerns\DefersLoading;
use App\Livewire\Concerns\HasReportCurrency;
use App\Services\Ledger\FinancialStatements as Statements;
use App\Services\Ledger\StatementExport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Statement of Financial Position and the Statement of Activities, from approved vouchers, with an optional
 * comparative period and the shared USD/SYP switch. Exports to XLSX and PDF (right to left in Arabic).
 */
#[Layout('components.layouts.app')]
class FinancialStatements extends Component
{
    use DefersLoading;
    use HasReportCurrency;

    #[Url]
    public string $asAt = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public bool $compare = true;

    public function mount(): void
    {
        $this->to = $this->date($this->to) ?? now()->toDateString();
        $this->asAt = $this->date($this->asAt) ?? $this->to;
        $this->from = $this->date($this->from) ?? now()->startOfYear()->toDateString();
    }

    public function export(string $format, Statements $statements, StatementExport $export): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $rtl = app()->getLocale() === 'ar';
        $headings = $this->headings();
        $rows = $this->rows($this->data($statements));
        $title = __('ledger.statements.title');
        $subtitle = __('ledger.statements.as_at').' '.$this->asAtDate().' · '.__('ledger.statements.period').' '.$this->fromDate().' – '.$this->toDate().' · '.$this->reportCurrency();

        if ($format === 'pdf') {
            $content = $export->pdf($title, $subtitle, $headings, $rows, $rtl);

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, 'financial-statements.pdf', ['Content-Type' => 'application/pdf']);
        }

        $path = $export->xlsx($title, $headings, $rows, $rtl);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, 'financial-statements.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function render(Statements $statements): View
    {
        return view('livewire.financial-statements', ['data' => $this->data($statements), 'unit' => $this->reportCurrency()]);
    }

    /** @return array{position: array<string, mixed>, activities: array<string, mixed>} */
    private function data(Statements $statements): array
    {
        $asAt = $this->asAtDate();
        $from = $this->fromDate();
        $to = $this->toDate();
        $compare = $this->compare;

        return [
            'position' => $statements->position($asAt, $compare ? $this->yearBefore($asAt) : null, $this->reportCurrency(), $this->reportRate()),
            'activities' => $statements->activities($from, $to, $compare ? $this->yearBefore($from) : null, $compare ? $this->yearBefore($to) : null, $this->reportCurrency(), $this->reportRate(), $compare),
        ];
    }

    /** @return list<string> */
    private function headings(): array
    {
        $unit = $this->reportCurrency();
        $headings = [__('ledger.statements.line'), $this->asAtDate().' ('.$unit.')'];

        return $this->compare ? [...$headings, $this->yearBefore($this->asAtDate()).' ('.$unit.')'] : $headings;
    }

    /**
     * @param  array{position: array<string, mixed>, activities: array<string, mixed>}  $data
     * @return list<array{0: string, 1: string|null, 2: string|null, 3: string}>
     */
    private function rows(array $data): array
    {
        $rows = [[__('ledger.statements.position'), null, null, 'title']];
        foreach ($data['position']['sections'] as $section) {
            $rows[] = [__('ledger.class.'.$section['key']), null, null, 'section'];
            foreach ($section['lines'] as $line) {
                $rows[] = [$this->lineLabel($line['label']), $line['current'], $line['previous'], 'line'];
            }
            $rows[] = [__('ledger.statements.total').' '.__('ledger.class.'.$section['key']), $section['current'], $section['previous'], 'total'];
        }
        $combined = $data['position']['liabilities_and_net_assets'];
        $rows[] = [__('ledger.statements.liabilities_and_net_assets'), $combined['current'], $combined['previous'], 'total'];

        $rows[] = [__('ledger.statements.activities').' ('.$this->fromDate().' – '.$this->toDate().')', null, null, 'title'];
        foreach ($data['activities']['sections'] as $section) {
            $rows[] = [__('ledger.class.'.$section['key']), null, null, 'section'];
            foreach ($section['lines'] as $line) {
                $rows[] = [$this->lineLabel($line['label']), $line['current'], $line['previous'], 'line'];
            }
            $rows[] = [__('ledger.statements.total').' '.__('ledger.class.'.$section['key']), $section['current'], $section['previous'], 'total'];
        }
        $rows[] = [__('ledger.statements.surplus'), $data['activities']['surplus']['current'], $data['activities']['surplus']['previous'], 'total'];

        return $rows;
    }

    /** A statement line is named by its English `statement_line`; Arabic has a translation for each, and an unknown line shows as stored. */
    public function lineLabel(string $label): string
    {
        if ($label === '__unclosed_result__') {
            return __('ledger.statements.unclosed_result');
        }

        $key = 'ledger.statement_lines.'.$label;
        $translated = __($key);

        return $translated === $key ? $label : $translated;
    }

    private function asAtDate(): string
    {
        return $this->date($this->asAt) ?? now()->toDateString();
    }

    private function fromDate(): string
    {
        return $this->date($this->from) ?? now()->startOfYear()->toDateString();
    }

    private function toDate(): string
    {
        return $this->date($this->to) ?? now()->toDateString();
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function yearBefore(string $date): string
    {
        return CarbonImmutable::parse($date)->subYearNoOverflow()->toDateString();
    }
}
