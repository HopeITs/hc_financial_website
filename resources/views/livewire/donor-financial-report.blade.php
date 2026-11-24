<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="donor-financial">
    <x-ui.report-loading />

    <x-ui.page-header :title="__('navigation.screens.rdonorfin')" :subtitle="__('donor_financial.intro')">
        <x-slot:actions>
            @if ($heading !== [])
                <x-ui.button icon="table-cells" wire:click="export('xlsx')" data-test="export-xlsx">{{ __('donor_financial.export_xlsx') }}</x-ui.button>
                <x-ui.button tone="primary" icon="document-arrow-down" wire:click="export('pdf')" data-test="export-pdf">{{ __('donor_financial.export_pdf') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

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
        <x-ui.panel class="flex flex-col gap-1 p-4 text-sm" data-test="df-heading">
            @foreach ($heading as $n => $line)
                <p @class(['font-semibold text-secondary-dark dark:text-ink' => $n === 0, 'text-secondary-medium dark:text-ink-muted' => $n > 0])>{{ $line }}</p>
            @endforeach
        </x-ui.panel>

        <x-ui.view-toggle wire-model="tab" :active="$tab" :label="__('donor_financial.reports')" class="max-w-full overflow-x-auto"
            :options="collect(\App\Livewire\DonorFinancialReport::TABS)->map(fn ($key) => ['key' => $key, 'label' => __('donor_financial.tabs.'.$key)])->all()" />

        @if ($bva !== null)
            <x-ui.panel :title="__('donor_financial.tabs.bva')" :subtitle="__('donor_financial.bva_hint')" data-test="df-bva">
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('donor_financial.col.line') }}</th>
                                <th>{{ __('donor_financial.col.currency') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.budget') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.before') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.period') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.to_date') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.remaining') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.percent') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.period_usd') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bva['lines'] as $line)
                                <tr wire:key="bva-{{ $line['key'] }}">
                                    <td>{{ $line['name'] }} <span class="type-meta text-secondary-medium dark:text-ink-muted" dir="ltr">{{ $line['account_code'] }}</span></td>
                                    <td>{{ $line['currency'] }}</td>
                                    <td class="fin-num">{{ number_format((float) $line['budget'], 2) }}</td>
                                    <td class="fin-num">{{ number_format((float) $line['before'], 2) }}</td>
                                    <td class="fin-num font-semibold">{{ number_format((float) $line['period'], 2) }}</td>
                                    <td class="fin-num">{{ number_format((float) $line['to_date'], 2) }}</td>
                                    <td @class(['fin-num', 'text-status-critical' => (float) $line['remaining'] < 0])>{{ number_format((float) $line['remaining'], 2) }}</td>
                                    <td class="fin-num">{{ $line['percent'] }}%</td>
                                    <td class="fin-num">{{ number_format((float) $line['period_usd'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="fin-empty">{{ __('donor_financial.no_lines') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($bva['totals'] !== [])
                            <tfoot>
                                @foreach ($bva['totals'] as $currency => $total)
                                    <tr class="font-semibold">
                                        <td>{{ __('donor_financial.total') }}</td>
                                        <td>{{ $currency }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['budget'], 2) }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['before'], 2) }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['period'], 2) }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['to_date'], 2) }}</td>
                                        <td class="fin-num">{{ number_format((float) $total['remaining'], 2) }}</td>
                                        <td class="fin-num">{{ $total['percent'] }}%</td>
                                        <td class="fin-num">{{ count($bva['totals']) === 1 ? number_format((float) $bva['period_usd'], 2) : '' }}</td>
                                    </tr>
                                @endforeach
                            </tfoot>
                        @endif
                    </table>
                </x-ui.scroll-table>
            </x-ui.panel>
        @endif

        @if ($expenses !== null)
            <x-ui.panel :title="__('donor_financial.tabs.expenses')" :count="count($expenses['rows'])" data-test="df-expenses">
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>{{ __('donor_financial.col.date') }}</th>
                                <th>{{ __('donor_financial.col.voucher') }}</th>
                                <th>{{ __('donor_financial.col.line') }}</th>
                                <th>{{ __('donor_financial.col.description') }}</th>
                                <th class="fin-num">{{ __('donor_financial.col.amount') }}</th>
                                <th class="fin-num">USD</th>
                                <th class="fin-num">{{ __('donor_financial.col.files') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenses['rows'] as $n => $row)
                                <tr wire:key="exp-{{ $n }}">
                                    <td class="tabular-nums">{{ $row['date'] }}</td>
                                    <td dir="ltr">{{ $row['voucher'] }}</td>
                                    <td>{{ $row['line'] }} <span class="type-meta text-secondary-medium dark:text-ink-muted" dir="ltr">{{ $row['account'] }}</span></td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="fin-num"><x-money :amount="$row['amount']" :currency="$row['currency']" /></td>
                                    <td class="fin-num">{{ number_format((float) $row['usd'], 2) }}</td>
                                    <td @class(['fin-num', 'text-status-warn' => $row['attachments'] === 0])>{{ $row['attachments'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="fin-empty">{{ __('donor_financial.no_expenses') }}</td></tr>
                            @endforelse
                        </tbody>
                        @if ($expenses['rows'] !== [])
                            <tfoot>
                                <tr class="font-semibold">
                                    <td colspan="4">{{ __('donor_financial.total') }}</td>
                                    <td class="fin-num">
                                        @foreach ($expenses['totals'] as $currency => $amount)
                                            <div><x-money :amount="$amount" :currency="$currency" /></div>
                                        @endforeach
                                    </td>
                                    <td class="fin-num">{{ number_format((float) $expenses['usd'], 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </x-ui.scroll-table>
            </x-ui.panel>
        @endif

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
    @endif
</div>
