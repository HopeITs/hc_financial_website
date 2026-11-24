<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\DonorFinancialReports;
use App\Services\Ledger\TableExport;
use App\Services\Projects\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The donor's financial report of a project for a period (finance requests of 2026-11-26), in the reports, page by
 * page as the donor receives it: the project's information, the summary by budget category in the donor's currency,
 * the list of expenses, budget against actual by line with the reason of each line going over or staying under, and
 * the donor's funds statement. The period is free, with quick picks. Exported as one Excel workbook, a sheet per page,
 * or one PDF on the letterhead with signature lines. A person limited to projects sees theirs.
 */
#[Layout('components.layouts.app')]
class DonorFinancialReport extends Component
{
    public const TABS = ['info', 'summary', 'expenses', 'bva', 'funds'];

    public const PRESETS = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'project'];

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'info')]
    public string $tab = 'info';

    public ?string $notice = null;

    public function mount(): void
    {
        $projects = $this->projects();

        if (! $projects->contains('code', $this->project)) {
            $this->project = (string) $projects->first()?->code;
        }

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'info';
        }

        if ($this->day($this->from) === null || $this->day($this->to) === null) {
            $this->preset('project');
        }
    }

    /** Only a project the person may report on; the period picked stays. */
    public function updatedProject(): void
    {
        abort_unless($this->projects()->contains('code', $this->project), 403);
    }

    public function preset(string $preset): void
    {
        abort_unless(in_array($preset, self::PRESETS, true), 404);
        $today = CarbonImmutable::today();
        $project = $this->current();

        [$from, $to] = match ($preset) {
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->firstOfQuarter(), $today],
            'last_quarter' => [$today->subQuarterNoOverflow()->firstOfQuarter(), $today->subQuarterNoOverflow()->lastOfQuarter()],
            'this_year' => [$today->startOfYear(), $today],
            default => [
                $project?->start_date !== null ? CarbonImmutable::parse($project->start_date) : $today->startOfYear(),
                $project?->end_date !== null && CarbonImmutable::parse($project->end_date)->lt($today) ? CarbonImmutable::parse($project->end_date) : $today,
            ],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    /** Writes why a line went over or stayed under its budget, for the period shown. */
    public function saveNote(string $key, string $note, DonorFinancialReports $reports): void
    {
        abort_unless($this->user()->canOnScreen('rdonorfin', ScreenAction::Edit) && $reports->notesAvailable(), 403);
        $project = $this->current();
        [$from, $to] = $this->period();
        abort_if($project === null || $from === null || $to === null, 404);
        $line = GlBudgetLine::query()->whereKey($key)->where('project_code', $project->code)->firstOrFail();

        $reports->saveNote($line, $from, $to, $note, (int) $this->user()->getAuthIdentifier());
        $this->notice = __('donor_financial.note_saved', ['line' => $line->name]);
    }

    public function export(string $format, DonorFinancialReports $reports, TableExport $export): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $project = $this->current();
        [$from, $to] = $this->period();
        abort_if($project === null || $from === null || $to === null, 404);

        $parts = $this->parts($project, $from, $to, $reports);
        $rtl = app()->getLocale() === 'ar';
        $name = 'donor-financial-'.$project->code.'-'.$from->toDateString().'-'.$to->toDateString();

        if ($format === 'pdf') {
            $content = $export->pdfBook($parts, $rtl, [__('donor_financial.sign.prepared'), __('donor_financial.sign.reviewed'), __('donor_financial.sign.approved')]);

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $name.'.pdf', ['Content-Type' => 'application/pdf']);
        }

        $path = $export->xlsxBook($parts, $rtl);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function render(DonorFinancialReports $reports): View
    {
        abort_unless($this->user()->canOnScreen('rdonorfin', ScreenAction::View), 403);
        $project = $this->current();
        [$from, $to] = $this->period();
        $ready = $project !== null && $from !== null && $to !== null && $from->lte($to);
        $shows = fn (string $tab): bool => $ready && $this->tab === $tab;

        return view('livewire.donor-financial-report', [
            'projects' => $this->projects(),
            'current' => $project,
            'badPeriod' => $from !== null && $to !== null && $from->gt($to),
            'info' => $shows('info') ? $reports->info($project, $from, $to) : null,
            'summary' => $shows('summary') ? $reports->categories($project, $from, $to) : null,
            'expenses' => $shows('expenses') ? $reports->expenses($project, $from, $to) : null,
            'bva' => $shows('bva') ? $reports->budgetVsActual($project, $from, $to) : null,
            'funds' => $shows('funds') ? $reports->funds($project, $from, $to) : null,
            'heading' => $ready ? $this->heading($project, $from, $to) : [],
            'canNote' => $this->user()->canOnScreen('rdonorfin', ScreenAction::Edit) && $reports->notesAvailable(),
        ]);
    }

    /**
     * The report's pages as the exports take them: a title, the lines under it, and its tables.
     *
     * @return list<array{title: string, sheet: string, lines: list<string>, sections: list<array{0: string, 1: list<string>, 2: list<list<string|null>>, 3: bool}>}>
     */
    private function parts(GlProject $project, CarbonImmutable $from, CarbonImmutable $to, DonorFinancialReports $reports): array
    {
        $t = fn (string $key, array $replace = []): string => (string) __('donor_financial.'.$key, $replace);
        $heading = $this->heading($project, $from, $to);
        $info = $reports->info($project, $from, $to);
        $summary = $reports->categories($project, $from, $to);
        $expenses = $reports->expenses($project, $from, $to);
        $bva = $reports->budgetVsActual($project, $from, $to);
        $funds = $reports->funds($project, $from, $to);
        $currency = $project->donor_currency;

        $infoRows = [
            [$t('info.code'), $project->code],
            [$t('info.name'), $project->name],
            [$t('donor'), (string) ($project->donor ?? '—')],
            [$t('contract'), (string) ($project->contract_number ?? '—')],
            [$t('funding_period'), ($project->start_date?->toDateString() ?? '—').' – '.($project->end_date?->toDateString() ?? '—')],
            [$t('report_period'), $from->toDateString().' – '.$to->toDateString()],
            [$t('info.currency'), $currency],
            [$t('info.budget', ['currency' => $currency]), $info['budget']],
            [$t('info.received', ['currency' => $currency]), $info['received']['donor']],
            [$t('info.spent', ['currency' => $currency]), $info['spent']['donor']],
            [$t('info.balance', ['currency' => $currency]), $info['balance']['donor']],
            [$t('info.percent'), $info['percent'].'%'],
            [$t('info.prepared_on'), CarbonImmutable::today()->toDateString()],
        ];

        $summaryRows = array_map(fn (array $row): array => [$row['category'], $row['budget'], $row['period'], $row['to_date'], $row['remaining'], $row['percent'].'%'], $summary['rows']);
        $summaryRows[] = [$t('total'), $summary['total']['budget'], $summary['total']['period'], $summary['total']['to_date'], $summary['total']['remaining'], $summary['total']['percent'].'%'];

        $donorColumn = $expenses['donor_currency'];
        $expenseHeadings = [$t('col.date'), $t('col.document_type'), $t('col.document_number'), $t('col.description_en'), 'SYP', 'USD',
            ...($donorColumn !== '' ? [$donorColumn] : []), $t('col.line'), $t('col.category'), $t('col.syp_rate'), ...($donorColumn !== '' ? [$t('col.donor_rate', ['currency' => $donorColumn])] : [])];
        $expenseRows = array_map(fn (array $row): array => [
            $row['date'], $row['document_type'] !== '' ? $t('document_types.'.$row['document_type']) : '', $row['document_number'], $row['description'], $row['syp'], $row['usd'],
            ...($donorColumn !== '' ? [$row['donor']] : []), $row['line'], $row['category'], $row['syp_rate'], ...($donorColumn !== '' ? [$row['donor_rate']] : []),
        ], $expenses['rows']);
        $expenseRows[] = [$t('total'), '', '', '', $expenses['totals']['syp'], $expenses['totals']['usd'], ...($donorColumn !== '' ? [$expenses['totals']['donor']] : []), '', '', '', ...($donorColumn !== '' ? [''] : [])];

        $bvaRows = array_map(fn (array $line): array => [
            $line['name'], $line['category'], $line['currency'], $line['budget'], $line['period'], $line['to_date'], $line['remaining'], $line['percent'].'%', $t('status.'.$line['status']), $line['note'],
        ], $bva['lines']);
        foreach ($bva['totals'] as $lineCurrency => $total) {
            $bvaRows[] = [$t('total').' '.$lineCurrency, '', $lineCurrency, $total['budget'], $total['period'], $total['to_date'], $total['remaining'], $total['percent'].'%', '', ''];
        }

        $fundRows = [
            [$t('funds.opening'), $funds['opening']['donor'], $funds['opening']['usd']],
            [$t('funds.received'), $funds['received']['donor'], $funds['received']['usd']],
            [$t('funds.spent'), $funds['spent']['donor'], $funds['spent']['usd']],
            [$t('funds.closing'), $funds['closing']['donor'], $funds['closing']['usd']],
        ];
        $receiptRows = array_map(fn (array $receipt): array => [$receipt['date'], $receipt['voucher'], $receipt['description'], $receipt['donor'], $receipt['usd']], $funds['receipts']);

        return [
            ['title' => $t('tabs.info'), 'sheet' => $t('sheets.info'), 'lines' => [], 'sections' => [
                [$t('tabs.info'), [$t('info.item'), $t('info.value')], $infoRows, false],
            ]],
            ['title' => $t('tabs.summary'), 'sheet' => $t('sheets.summary'), 'lines' => $heading, 'sections' => [
                [$t('summary_in', ['currency' => $currency]), [$t('col.category'), $t('col.budget'), $t('col.period'), $t('col.to_date'), $t('col.remaining'), $t('col.percent')], $summaryRows, true],
            ]],
            ['title' => $t('tabs.expenses'), 'sheet' => $t('sheets.expenses'), 'lines' => $heading, 'sections' => [
                [$t('tabs.expenses'), $expenseHeadings, $expenseRows, true],
            ]],
            ['title' => $t('tabs.bva'), 'sheet' => $t('sheets.bva'), 'lines' => $heading, 'sections' => [
                [$t('tabs.bva'), [$t('col.line'), $t('col.category'), $t('col.currency'), $t('col.budget'), $t('col.period'), $t('col.to_date'), $t('col.remaining'), $t('col.percent'), $t('col.status'), $t('col.note')], $bvaRows, false],
            ]],
            ['title' => $t('tabs.funds'), 'sheet' => $t('sheets.funds'), 'lines' => [...$heading, ...($funds['rate'] !== null ? [$t('funds.rate', ['currency' => $currency, 'rate' => $funds['rate']])] : [])], 'sections' => [
                [$t('tabs.funds'), ['', $currency, 'USD'], $fundRows, true],
                [$t('funds.receipts'), [$t('col.date'), $t('col.voucher'), $t('col.description'), $currency, 'USD'], $receiptRows, false],
            ]],
        ];
    }

    /** @return list<string> the project, its donor and agreement, its funding period and the report's period */
    private function heading(GlProject $project, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(array_filter([
            $project->code.' · '.$project->name,
            __('donor_financial.donor').': '.($project->donor ?? '—').' ('.$project->donor_currency.')',
            $project->contract_number ? __('donor_financial.contract').': '.$project->contract_number : null,
            __('donor_financial.funding_period').': '.($project->start_date?->toDateString() ?? '—').' – '.($project->end_date?->toDateString() ?? '—'),
            __('donor_financial.report_period').': '.$from->toDateString().' – '.$to->toDateString(),
        ]));
    }

    /** @return Collection<int, GlProject> the projects the person may report on */
    private function projects(): Collection
    {
        $limit = app(ProjectAccess::class)->limitedTo($this->user());

        return GlProject::query()->where('is_general_account', false)
            ->when($limit !== null, fn ($query) => $query->whereIn('legacy_account_id', $limit ?? []))
            ->orderBy('code')->get();
    }

    private function current(): ?GlProject
    {
        return $this->projects()->firstWhere('code', $this->project);
    }

    /** @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null} */
    private function period(): array
    {
        return [$this->day($this->from), $this->day($this->to)];
    }

    private function day(string $text): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $text);

        return $day === false ? null : $day;
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
