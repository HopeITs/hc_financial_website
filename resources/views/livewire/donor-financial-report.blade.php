<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="donor-financial">
    <x-ui.report-loading />

    <x-ui.page-header :title="__('navigation.screens.rdonorfin')" :subtitle="__('donor_financial.intro')">
        <x-slot:actions>
            @if ($monthly !== null)
                <x-ui.button icon="table-cells" wire:click="exportMonthly('xlsx')" data-test="export-monthly-xlsx">{{ __('donor_financial.export_xlsx') }}</x-ui.button>
                <x-ui.button tone="primary" icon="document-arrow-down" wire:click="exportMonthly('pdf')" data-test="export-monthly-pdf">{{ __('donor_financial.export_pdf') }}</x-ui.button>
            @elseif ($heading !== [])
                <x-ui.button icon="table-cells" wire:click="export('xlsx')" data-test="export-xlsx">{{ __('donor_financial.export_xlsx') }}</x-ui.button>
                <x-ui.button tone="primary" icon="document-arrow-down" wire:click="export('pdf')" data-test="export-pdf">{{ __('donor_financial.export_pdf') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($notice)
        <div class="rounded-lg border border-status-good bg-status-good-soft px-4 py-2 text-sm text-status-good" role="status" data-test="notice">{{ $notice }}</div>
    @endif

    <x-ui.panel class="flex flex-col gap-4 p-4" data-test="donor-financial-filters">
        <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.form-select wire:model.live="project" :label="__('donor_financial.project')" class="lg:col-span-2" data-test="df-project">
                @foreach ($projects as $option)
                    <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
                @endforeach
            </x-ui.form-select>
            <x-ui.form-input type="date" wire:model.live="from" :label="__('donor_financial.from')" data-test="df-from" />
            <x-ui.form-input type="date" wire:model.live="to" :label="__('donor_financial.to')" data-test="df-to" />
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Livewire\DonorFinancialReport::PRESETS as $preset)
                <x-ui.button size="sm" tone="ghost" wire:click="preset('{{ $preset }}')" data-test="preset-{{ $preset }}">{{ __('donor_financial.presets.'.$preset) }}</x-ui.button>
            @endforeach
        </div>
    </x-ui.panel>

    @if ($projects->isEmpty())
        <x-ui.empty-state icon="folder-open" :heading="__('donor_financial.no_projects')" :body="__('donor_financial.no_projects_hint')" />
    @elseif ($badPeriod)
        <p class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="bad-period">{{ __('donor_financial.bad_period') }}</p>
    @else
        <x-ui.view-toggle wire-model="tab" :active="$tab" :label="__('donor_financial.reports')" class="max-w-full overflow-x-auto"
            :options="collect(\App\Livewire\DonorFinancialReport::TABS)->map(fn ($key) => ['key' => $key, 'label' => __('donor_financial.tabs.'.$key)])->all()" />

        {{-- 1. The project's information --}}
        @if ($info !== null)
            <x-ui.panel :title="__('donor_financial.tabs.info')" data-test="df-info">
                <dl class="grid gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.info.name') }}</dt><dd class="font-medium">{{ $current->code }} · {{ $current->name }}</dd></div>
                    <div><dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.donor') }}</dt><dd class="font-medium">{{ $current->donor ?? '—' }}</dd></div>
                    <div><dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.contract') }}</dt><dd class="font-medium" dir="ltr">{{ $current->contract_number ?? '—' }}</dd></div>
                    <div><dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.funding_period') }}</dt><dd class="font-medium tabular-nums" dir="ltr">{{ $current->start_date?->toDateString() ?? '—' }} – {{ $current->end_date?->toDateString() ?? '—' }}</dd></div>
                    <div><dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.report_period') }}</dt><dd class="font-medium tabular-nums" dir="ltr">{{ $from }} – {{ $to }}</dd></div>
                    <div><dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.info.currency') }}</dt><dd class="font-medium">{{ $info['currency'] }}</dd></div>
                </dl>
                <div class="grid gap-px border-t border-secondary-soft bg-secondary-soft sm:grid-cols-2 lg:grid-cols-5 dark:border-line dark:bg-line">
                    @foreach (['budget' => $info['budget'], 'received' => $info['received']['donor'], 'spent' => $info['spent']['donor'], 'balance' => $info['balance']['donor']] as $key => $amount)
                        <div class="flex flex-col gap-1 bg-white p-4 dark:bg-surface-1">
                            <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.info.'.$key, ['currency' => $info['currency']]) }}</span>
                            <span class="text-lg font-semibold tabular-nums"><x-money :amount="$amount" :currency="$info['currency']" /></span>
                        </div>
                    @endforeach
                    <div class="flex flex-col gap-1 bg-white p-4 dark:bg-surface-1">
                        <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.info.percent') }}</span>
                        <span class="text-lg font-semibold tabular-nums">{{ $info['percent'] }}%</span>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        {{-- 2. The summary by budget category, in the donor's currency --}}
        @if ($summary !== null)
            <x-ui.panel :title="__('donor_financial.tabs.summary')" :subtitle="__('donor_financial.summary_in', ['currency' => $summary['currency']])" data-test="df-summary">
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('donor_financial.col.category') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.budget') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.period') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.to_date') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.remaining') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.percent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['rows'] as $n => $row)
                                <tr wire:key="cat-{{ $n }}" data-test="category-row">
                                    <td>{{ $row['category'] }}</td>
                                    <td class="fin-num">{{ number_format((float) $row['budget'], 2) }}</td>
                                    <td class="fin-num">{{ number_format((float) $row['period'], 2) }}</td>
                                    <td class="fin-num">{{ number_format((float) $row['to_date'], 2) }}</td>
                                    <td @class(['fin-num', 'text-status-critical' => (float) $row['remaining'] < 0])>{{ number_format((float) $row['remaining'], 2) }}</td>
                                    <td class="fin-num">{{ $row['percent'] }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="fin-empty">{{ __('donor_financial.no_lines') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td>{{ __('donor_financial.total') }}</td>
                                <td class="fin-num">{{ number_format((float) $summary['total']['budget'], 2) }}</td>
                                <td class="fin-num">{{ number_format((float) $summary['total']['period'], 2) }}</td>
                                <td class="fin-num">{{ number_format((float) $summary['total']['to_date'], 2) }}</td>
                                <td class="fin-num">{{ number_format((float) $summary['total']['remaining'], 2) }}</td>
                                <td class="fin-num">{{ $summary['total']['percent'] }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </x-ui.scroll-table>
            </x-ui.panel>
        @endif

        {{-- 3. The list of expenses --}}
        @if ($expenses !== null)
            @php($donorColumn = $expenses['donor_currency'])
            <x-ui.panel :title="__('donor_financial.tabs.expenses')" :count="count($expenses['rows'])" data-test="df-expenses">
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('donor_financial.col.date') }}</th>
                                <th>{{ __('donor_financial.col.document_type') }}</th>
                                <th>{{ __('donor_financial.col.document_number') }}</th>
                                <th class="min-w-56">{{ __('donor_financial.col.description_en') }}</th>
                                <th class="fin-num">SYP</th>
                                <th class="fin-num">USD</th>
                                @if ($donorColumn !== '')<th class="fin-num">{{ $donorColumn }}</th>@endif
                                <th>{{ __('donor_financial.col.line') }}</th>
                                <th>{{ __('donor_financial.col.category') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.syp_rate') }}</th>
                                @if ($donorColumn !== '')<th class="fin-num">{{ __('donor_financial.col.donor_rate', ['currency' => $donorColumn]) }}</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenses['rows'] as $n => $row)
                                <tr wire:key="exp-{{ $n }}">
                                    <td class="tabular-nums">{{ $row['date'] }}</td>
                                    <td>{{ $row['document_type'] !== '' ? __('donor_financial.document_types.'.$row['document_type']) : '—' }}</td>
                                    <td dir="ltr">{{ $row['document_number'] !== '' ? $row['document_number'] : '—' }}</td>
                                    <td dir="auto">
                                        {{ $row['description'] }}
                                        <a href="{{ route('entries.vouchers', ['voucher' => $row['voucher']]) }}" wire:navigate class="block text-xs text-secondary-medium underline dark:text-ink-muted" dir="ltr">{{ $row['voucher'] }}</a>
                                    </td>
                                    <td class="fin-num">{{ $row['syp'] !== null ? number_format((float) $row['syp'], 2) : '—' }}</td>
                                    <td class="fin-num">{{ number_format((float) $row['usd'], 2) }}</td>
                                    @if ($donorColumn !== '')<td class="fin-num">{{ $row['donor'] !== null ? number_format((float) $row['donor'], 2) : '—' }}</td>@endif
                                    <td>{{ $row['line'] }}</td>
                                    <td>{{ $row['category'] }}</td>
                                    <td class="fin-num">{{ $row['syp_rate'] !== null ? rtrim(rtrim($row['syp_rate'], '0'), '.') : '—' }}</td>
                                    @if ($donorColumn !== '')<td class="fin-num">{{ $row['donor_rate'] !== null ? rtrim(rtrim($row['donor_rate'], '0'), '.') : '—' }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="11" class="fin-empty">{{ __('donor_financial.no_expenses') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($expenses['rows'] !== [])
                            <tfoot>
                                <tr class="font-semibold" data-test="expense-totals">
                                    <td colspan="4">{{ __('donor_financial.total') }}</td>
                                    <td class="fin-num">{{ number_format((float) $expenses['totals']['syp'], 2) }}</td>
                                    <td class="fin-num">{{ number_format((float) $expenses['totals']['usd'], 2) }}</td>
                                    @if ($donorColumn !== '')<td class="fin-num">{{ number_format((float) $expenses['totals']['donor'], 2) }}</td>@endif
                                    <td colspan="{{ $donorColumn !== '' ? 4 : 3 }}"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </x-ui.scroll-table>
            </x-ui.panel>
        @endif

        {{-- 4. Budget against actual by line, with the reason of going over or staying under --}}
        @if ($bva !== null)
            <x-ui.panel :title="__('donor_financial.tabs.bva')" :subtitle="__('donor_financial.bva_hint')" data-test="df-bva">
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('donor_financial.col.line') }}</th>
                                <th>{{ __('donor_financial.col.category') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.budget') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.period') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.to_date') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.remaining') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.percent') }}</th>
                                <th class="min-w-64">{{ __('donor_financial.col.note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bva['lines'] as $line)
                                <tr wire:key="bva-{{ md5($line['key']) }}">
                                    <td>{{ $line['name'] }} <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ $line['currency'] }}</span></td>
                                    <td>{{ $line['category'] }}</td>
                                    <td class="fin-num">{{ number_format((float) $line['budget'], 2) }}</td>
                                    <td class="fin-num font-semibold">{{ number_format((float) $line['period'], 2) }}</td>
                                    <td class="fin-num">{{ number_format((float) $line['to_date'], 2) }}</td>
                                    <td @class(['fin-num', 'text-status-critical' => $line['status'] === 'over'])>{{ number_format((float) $line['remaining'], 2) }}</td>
                                    <td class="fin-num">
                                        {{ $line['percent'] }}%
                                        <x-ui.status-badge :status="$line['status'] === 'over' ? 'rejected' : ($line['status'] === 'on' ? 'done' : 'approved')" :label="__('donor_financial.status.'.$line['status'])" />
                                    </td>
                                    <td>
                                        @if ($canNote)
                                            <x-ui.input size="sm" :value="$line['note']" :placeholder="__('donor_financial.note_placeholder')" :aria-label="__('donor_financial.col.note')"
                                                wire:change="saveNote(@js($line['key']), $event.target.value)" data-test="note-{{ md5($line['key']) }}" />
                                        @else
                                            {{ $line['note'] !== '' ? $line['note'] : '—' }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="fin-empty">{{ __('donor_financial.no_lines') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($bva['totals'] !== [])
                            <tfoot>
                                @foreach ($bva['totals'] as $currency => $total)
                                    <tr class="font-semibold">
                                        <td colspan="2">{{ __('donor_financial.total') }} {{ $currency }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['budget'], 2) }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['period'], 2) }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['to_date'], 2) }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['remaining'], 2) }}</td>
                                        <td class="fin-num">{{ $total['percent'] }}%</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            </tfoot>
                        @endif
                    </table>
                </x-ui.scroll-table>
            </x-ui.panel>
        @endif

        {{-- 5. The donor's funds statement --}}
        @if ($funds !== null)
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="df-funds">
                @foreach (['opening', 'received', 'spent', 'closing'] as $part)
                    <x-ui.panel class="flex flex-col gap-1 p-4">
                        <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.funds.'.$part) }}</span>
                        <span class="text-lg font-semibold tabular-nums"><x-money :amount="$funds[$part]['donor']" :currency="$funds['currency']" /></span>
                        <span class="type-meta tabular-nums text-secondary-medium dark:text-ink-muted"><x-money :amount="$funds[$part]['usd']" currency="USD" /></span>
                    </x-ui.panel>
                @endforeach
            </div>
            @if ($funds['rate'] !== null)
                <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.funds.rate', ['currency' => $funds['currency'], 'rate' => $funds['rate']]) }}</p>
            @endif
            <x-ui.panel :title="__('donor_financial.funds.receipts')" :count="count($funds['receipts'])">
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('donor_financial.col.date') }}</th>
                                <th>{{ __('donor_financial.col.voucher') }}</th>
                                <th>{{ __('donor_financial.col.description') }}</th>
                                <th class="fin-num">{{ $funds['currency'] }}</th>
                                <th class="fin-num">USD</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($funds['receipts'] as $receipt)
                                <tr wire:key="rcpt-{{ $receipt['voucher'] }}">
                                    <td class="tabular-nums">{{ $receipt['date'] }}</td>
                                    <td dir="ltr">{{ $receipt['voucher'] }}</td>
                                    <td>{{ $receipt['description'] }}</td>
                                    <td class="fin-num">{{ number_format((float) $receipt['donor'], 2) }}</td>
                                    <td class="fin-num">{{ number_format((float) $receipt['usd'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="fin-empty">{{ __('donor_financial.no_receipts') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.scroll-table>
            </x-ui.panel>
        @endif

        {{-- 6. The monthly report on the finance form: budget against actual in dollars, by category and by line --}}
        @if ($tab === 'monthly')
            @php
                $pct = fn (?string $share): string => $share === null ? '—' : bcmul($share, '100', 1).'%';
                $tone = fn (string $status): string => match ($status) { 'over', 'unbudgeted' => 'rejected', 'near', 'slow' => 'pending', 'ok' => 'done', default => 'draft' };
            @endphp
            <div class="flex flex-wrap items-end gap-4">
                <x-ui.form-input type="month" wire:model.live="month" :label="__('donor_financial.monthly.reporting_month')" data-test="df-month" />
                <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.monthly.hint') }}</p>
            </div>

            @if ($monthly !== null)
                @php($report = $monthly['report'])
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-6" data-test="df-monthly-kpis">
                    <x-ui.stat-tile :label="__('donor_financial.monthly.col.budget')" :value="number_format((float) $report['totals']['budget'], 2)" unit="USD" icon="banknotes" />
                    <x-ui.stat-tile :label="__('donor_financial.monthly.col.month')" :value="number_format((float) $report['totals']['month'], 2)" unit="USD" icon="calendar" />
                    <x-ui.stat-tile :label="__('donor_financial.monthly.col.to_date')" :value="number_format((float) $report['totals']['to_date'], 2)" unit="USD" icon="chart-bar" />
                    <x-ui.stat-tile :label="__('donor_financial.monthly.col.remaining')" :value="number_format((float) $report['totals']['remaining'], 2)" unit="USD" icon="scale" />
                    <x-ui.stat-tile :label="__('donor_financial.monthly.col.burn')" :value="$pct($report['totals']['burn'])" icon="fire" />
                    <x-ui.stat-tile :label="__('donor_financial.monthly.col.time')" :value="$pct($report['time'])" icon="clock" />
                </div>

                @if ($report['to_explain'] > 0)
                    <p class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" role="alert" data-test="df-monthly-explain">{{ trans_choice('donor_financial.monthly.to_explain', $report['to_explain'], ['count' => $report['to_explain']]) }}</p>
                @endif

                <x-ui.panel :title="__('donor_financial.monthly.by_category')" data-test="df-monthly-categories">
                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('donor_financial.monthly.col.category') }}</th>
                                    @foreach (['budget', 'previous', 'month', 'to_date', 'remaining', 'burn'] as $column)
                                        <th class="fin-num">{{ __('donor_financial.monthly.col.'.$column) }}</th>
                                    @endforeach
                                    <th>{{ __('donor_financial.monthly.col.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['categories'] as $row)
                                    <tr wire:key="mcat-{{ md5($row['category']) }}">
                                        <td>{{ $row['category'] }}</td>
                                        @foreach (['budget', 'previous', 'month', 'to_date', 'remaining'] as $column)
                                            <td class="fin-num"><x-money :amount="$row[$column]" /></td>
                                        @endforeach
                                        <td class="fin-num">{{ $pct($row['burn']) }}</td>
                                        <td><x-ui.status-badge :status="$tone($row['status'])" :label="__('donor_financial.monthly.status.'.$row['status'])" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold">
                                    <td>{{ __('donor_financial.monthly.total_categories') }}</td>
                                    @foreach (['budget', 'previous', 'month', 'to_date', 'remaining'] as $column)
                                        <td class="fin-num"><x-money :amount="$report['totals'][$column]" /></td>
                                    @endforeach
                                    <td class="fin-num">{{ $pct($report['totals']['burn']) }}</td>
                                    <td><x-ui.status-badge :status="$tone($report['totals']['status'])" :label="__('donor_financial.monthly.status.'.$report['totals']['status'])" /></td>
                                </tr>
                            </tfoot>
                        </table>
                    </x-ui.scroll-table>
                </x-ui.panel>

                <x-ui.panel :title="__('donor_financial.monthly.by_line')" :subtitle="__('donor_financial.monthly.explain_hint')" data-test="df-monthly-lines">
                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('donor_financial.monthly.col.line') }}</th>
                                    @foreach (['budget', 'previous', 'month', 'to_date', 'remaining', 'burn'] as $column)
                                        <th class="fin-num">{{ __('donor_financial.monthly.col.'.$column) }}</th>
                                    @endforeach
                                    <th>{{ __('donor_financial.monthly.col.status') }}</th>
                                    <th class="min-w-64">{{ __('donor_financial.monthly.col.explanation') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($report['lines'] as $line)
                                    <tr wire:key="mline-{{ md5($line['key']) }}">
                                        <td>{{ $line['name'] }} <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ $line['category'] }}</span></td>
                                        @foreach (['budget', 'previous', 'month', 'to_date', 'remaining'] as $column)
                                            <td class="fin-num"><x-money :amount="$line[$column]" /></td>
                                        @endforeach
                                        <td class="fin-num">{{ $pct($line['burn']) }}</td>
                                        <td><x-ui.status-badge :status="$tone($line['status'])" :label="__('donor_financial.monthly.status.'.$line['status'])" /></td>
                                        <td>
                                            @if ($canNote)
                                                <x-ui.input size="sm" :value="$line['note']" :placeholder="__('donor_financial.note_placeholder')" :aria-label="__('donor_financial.monthly.col.explanation')"
                                                    wire:change="saveMonthlyNote(@js($line['key']), $event.target.value)" data-test="mnote-{{ md5($line['key']) }}" />
                                            @else
                                                {{ $line['note'] !== '' ? $line['note'] : '—' }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="fin-empty">{{ __('donor_financial.no_lines') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-ui.scroll-table>
                </x-ui.panel>

                <x-ui.panel :title="__('donor_financial.monthly.breakdown')" data-test="df-monthly-breakdown">
                    <x-ui.scroll-table :paginate="false">
                        <table class="fin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('donor_financial.monthly.col.line') }}</th>
                                    <th class="fin-num">{{ __('donor_financial.monthly.col.budget') }}</th>
                                    <th class="fin-num">{{ __('donor_financial.monthly.col.spent') }}</th>
                                    @foreach ($monthly['breakdown']['months'] as $key)
                                        <th class="fin-num" dir="ltr">{{ \Carbon\CarbonImmutable::parse($key.'-01')->format('m/Y') }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($monthly['breakdown']['rows'] as $row)
                                    <tr wire:key="mbd-{{ md5($row['key']) }}">
                                        <td>{{ $row['name'] }}</td>
                                        <td class="fin-num"><x-money :amount="$row['budget']" /></td>
                                        <td class="fin-num"><x-money :amount="$row['spent']" /></td>
                                        @foreach ($row['by_month'] as $amount)
                                            <td class="fin-num"><x-money :amount="$amount" /></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-ui.scroll-table>
                </x-ui.panel>

                @if ($monthly['beneficiaries'] !== [])
                    <x-ui.panel :title="__('donor_financial.monthly.beneficiaries')" :count="count($monthly['beneficiaries'])" data-test="df-monthly-beneficiaries">
                        <x-ui.scroll-table :paginate="false">
                            <table class="fin-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('donor_financial.monthly.col.beneficiary_no') }}</th>
                                        <th>{{ __('donor_financial.monthly.col.beneficiary') }}</th>
                                        <th class="fin-num">{{ __('donor_financial.monthly.col.syp') }}</th>
                                        <th class="fin-num">{{ __('donor_financial.monthly.col.usd') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monthly['beneficiaries'] as $index => $row)
                                        <tr wire:key="mben-{{ $index }}">
                                            <td dir="ltr">{{ $row['number'] }}</td>
                                            <td>{{ $row['name'] }}</td>
                                            <td class="fin-num"><x-money :amount="$row['syp']" /></td>
                                            <td class="fin-num"><x-money :amount="$row['usd']" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-ui.scroll-table>
                    </x-ui.panel>
                @endif

                <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('donor_financial.monthly.legend') }}</p>
            @endif
        @endif
    @endif
</div>
