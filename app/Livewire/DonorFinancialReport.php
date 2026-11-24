<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
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
 * The donor's financial reports of a project for a period (finance request of 2026-11-26), in the reports: budget
 * against actual, the expenses' detail and the funds statement. The period is free, with quick picks (this month,
 * last month, this or last quarter, this year, the whole project). Exported as one Excel workbook, a sheet per report,
 * or one PDF on the letterhead with signature lines, in the page's language. A person limited to projects sees theirs.
 */
#[Layout('components.layouts.app')]
class DonorFinancialReport extends Component
{
    public const TABS = ['bva', 'expenses', 'funds'];

    public const PRESETS = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'project'];

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'bva')]
    public string $tab = 'bva';

    public function mount(): void
    {
        $projects = $this->projects();

        if (! $projects->contains('code', $this->project)) {
            $this->project = (string) $projects->first()?->code;
        }

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'bva';
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

        return view('livewire.donor-financial-report', [
            'projects' => $this->projects(),
            'current' => $project,
            'badPeriod' => $from !== null && $to !== null && $from->gt($to),
            'bva' => $ready && $this->tab === 'bva' ? $reports->budgetVsActual($project, $from, $to) : null,
            'expenses' => $ready && $this->tab === 'expenses' ? $reports->expenses($project, $from, $to) : null,
            'funds' => $ready && $this->tab === 'funds' ? $reports->funds($project, $from, $to) : null,
            'heading' => $ready ? $this->heading($project, $from, $to) : [],
        ]);
    }

    /**
     * The three reports as the exports take them: a title, the lines under it, and its tables.
     *
     * @return list<array{title: string, sheet: string, lines: list<string>, sections: list<array{0: string, 1: list<string>, 2: list<list<string|null>>, 3: bool}>}>
     */
    private function parts(GlProject $project, CarbonImmutable $from, CarbonImmutable $to, DonorFinancialReports $reports): array
    {
        $heading = $this->heading($project, $from, $to);
        $bva = $reports->budgetVsActual($project, $from, $to);
        $expenses = $reports->expenses($project, $from, $to);
        $funds = $reports->funds($project, $from, $to);
        $t = fn (string $key, array $replace = []): string => (string) __('donor_financial.'.$key, $replace);

        $bvaRows = array_map(fn (array $line): array => [
            $line['name'], $line['account_code'], $line['currency'], $line['budget'], $line['before'], $line['period'], $line['to_date'], $line['remaining'], $line['percent'].'%', $line['period_usd'],
        ], $bva['lines']);
        foreach ($bva['totals'] as $currency => $total) {
            $bvaRows[] = [$t('total').' '.$currency, '', $currency, $total['budget'], $total['before'], $total['period'], $total['to_date'], $total['remaining'], $total['percent'].'%', count($bva['totals']) === 1 ? $bva['period_usd'] : null];
        }

        $expenseRows = array_map(fn (array $row): array => [
            $row['date'], $row['voucher'], $row['line'], $row['account'], $row['description'], $row['currency'], $row['amount'], $row['usd'], (string) $row['attachments'],
        ], $expenses['rows']);
        $expenseRows[] = [$t('total'), '', '', '', '', '', count($expenses['totals']) === 1 ? (string) array_values($expenses['totals'])[0] : null, $expenses['usd'], ''];

        $currency = $funds['currency'];
        $fundRows = [
            [$t('funds.opening'), $funds['opening']['donor'], $funds['opening']['usd']],
            [$t('funds.received'), $funds['received']['donor'], $funds['received']['usd']],
            [$t('funds.spent'), $funds['spent']['donor'], $funds['spent']['usd']],
            [$t('funds.closing'), $funds['closing']['donor'], $funds['closing']['usd']],
        ];
        $receiptRows = array_map(fn (array $receipt): array => [$receipt['date'], $receipt['voucher'], $receipt['description'], $receipt['donor'], $receipt['usd']], $funds['receipts']);

        return [
            ['title' => $t('tabs.bva'), 'sheet' => $t('sheets.bva'), 'lines' => $heading, 'sections' => [
                [$t('tabs.bva'), [$t('col.line'), $t('col.account'), $t('col.currency'), $t('col.budget'), $t('col.before'), $t('col.period'), $t('col.to_date'), $t('col.remaining'), $t('col.percent'), $t('col.period_usd')], $bvaRows, $bva['totals'] !== []],
            ]],
            ['title' => $t('tabs.expenses'), 'sheet' => $t('sheets.expenses'), 'lines' => $heading, 'sections' => [
                [$t('tabs.expenses'), [$t('col.date'), $t('col.voucher'), $t('col.line'), $t('col.account'), $t('col.description'), $t('col.currency'), $t('col.amount'), 'USD', $t('col.files')], $expenseRows, true],
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
            ->orderBy('code')->get(['code', 'name', 'donor', 'donor_currency', 'contract_number', 'start_date', 'end_date', 'legacy_account_id']);
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
