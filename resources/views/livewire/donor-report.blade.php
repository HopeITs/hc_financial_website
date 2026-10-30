<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.report-loading />

    <x-ui.page-header :title="__('navigation.screens.rdonor')" :subtitle="__('ledger.donor_report.intro')">
        <x-slot:actions>
            @if ($report)
                <x-ui.button wire:click="export('xlsx')" data-test="export-xlsx">{{ __('ledger.statements.xlsx') }}</x-ui.button>
                <x-ui.button tone="primary" wire:click="export('pdf')" data-test="export-pdf">{{ __('ledger.statements.pdf') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-end gap-4 sm:grid-cols-2" data-test="donor-filters">
        <x-ui.form-select wire:model.live="project" :label="__('ledger.transfers.project')">
            @foreach ($projects as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
            @endforeach
        </x-ui.form-select>
    </div>

    @if ($report === null)
        <p class="text-sm text-ink-muted" data-test="donor-empty">{{ __('ledger.donor_report.no_project') }}</p>
    @else
        @php($project = $report['project'])
        @php($currency = $project->donor_currency)

        <x-ui.panel class="grid gap-x-6 gap-y-3 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="donor-summary">
            <div class="flex flex-col gap-0.5">
                <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.donor_report.donor') }}</span>
                <span class="font-medium">{{ $project->donor ?? '—' }}</span>
                @if ($project->contract_number)
                    <span class="type-meta text-secondary-medium dark:text-ink-muted" dir="ltr">{{ $project->contract_number }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.donor_report.period') }}</span>
                <span class="font-medium tabular-nums" dir="ltr">{{ $project->start_date?->toDateString() ?? '—' }} – {{ $project->end_date?->toDateString() ?? '—' }}</span>
                @if ($report['after_end'])
                    <span class="type-meta text-status-warn" data-test="after-end">{{ __('ledger.donor_report.after_end') }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.donor_report.received') }}</span>
                <span class="font-semibold tabular-nums" dir="ltr" data-test="received-donor"><x-money :amount="$report['received']['donor']" /> <span class="text-xs text-ink-muted">{{ $currency }}</span></span>
                <span class="type-meta tabular-nums text-secondary-medium dark:text-ink-muted" dir="ltr"><x-money :amount="$report['received']['usd']" /> USD
                    @if ($report['rate'] !== null && $currency !== 'USD')
                        · {{ __('ledger.donor_report.rate_short', ['currency' => $currency]) }} {{ rtrim(rtrim(number_format((float) $report['rate'], 6, '.', ''), '0'), '.') }}
                    @endif
                </span>
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.donor_report.balance') }}</span>
                <span class="font-semibold tabular-nums" dir="ltr" data-test="balance-donor"><x-money :amount="$report['balance']['donor']" /> <span class="text-xs text-ink-muted">{{ $currency }}</span></span>
                <span class="type-meta tabular-nums text-secondary-medium dark:text-ink-muted" dir="ltr"><x-money :amount="$report['balance']['usd']" /> USD</span>
            </div>
        </x-ui.panel>

        <section class="flex flex-col gap-3" aria-labelledby="donor-lines">
            <h2 id="donor-lines" class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.donor_report.lines') }}</h2>
            <x-ui.scroll-table>
                <table class="fin-table" data-test="donor-lines">
                    <thead>
                        <tr>
                            <th>{{ __('ledger.donor_report.line') }}</th>
                            <th>{{ __('ledger.builder.account') }}</th>
                            <th class="fin-num">{{ __('ledger.donor_report.budget') }}</th>
                            <th class="fin-num">{{ __('ledger.donor_report.spent') }}</th>
                            <th class="fin-num">{{ __('ledger.donor_report.remaining') }}</th>
                            <th class="fin-num">{{ __('ledger.donor_report.percent') }}</th>
                            <th class="fin-num">{{ __('ledger.donor_report.spent_usd') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['lines'] as $line)
                            <tr wire:key="donor-line-{{ $line['key'] }}">
                                <td class="fin-wrap">{{ $line['name'] }} <span class="text-xs text-ink-muted" dir="ltr">{{ $line['currency'] }}</span></td>
                                <td dir="ltr" class="text-start">{{ $line['account_code'] }}</td>
                                <td class="fin-num"><x-money :amount="$line['budget']" /></td>
                                <td class="fin-num"><x-money :amount="$line['actual']" /></td>
                                <td @class(['fin-num', 'text-status-critical' => bccomp($line['remaining'], '0', 2) < 0])><x-money :amount="$line['remaining']" /></td>
                                <td class="fin-num tabular-nums" dir="ltr">{{ $line['percent'] }}%</td>
                                <td class="fin-num"><x-money :amount="$line['usd']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="fin-empty">{{ __('ledger.donor_report.no_lines') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($report['totals'] !== [])
                        <tfoot>
                            @foreach ($report['totals'] as $code => $total)
                                <tr data-test="donor-total-{{ $code }}">
                                    <td colspan="2">{{ __('ledger.donor_report.total') }} <span class="text-xs text-ink-muted" dir="ltr">{{ $code }}</span></td>
                                    <td class="fin-num"><x-money :amount="$total['budget']" /></td>
                                    <td class="fin-num"><x-money :amount="$total['actual']" /></td>
                                    <td class="fin-num"><x-money :amount="$total['remaining']" /></td>
                                    <td class="fin-num tabular-nums" dir="ltr">{{ $total['percent'] }}%</td>
                                    <td class="fin-num">
                                        @if ($loop->first)
                                            <x-money :amount="$report['spent_usd']" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tfoot>
                    @endif
                </table>
            </x-ui.scroll-table>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="donor-receipts">
            <h2 id="donor-receipts" class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.donor_report.receipts') }}</h2>
            <x-ui.scroll-table>
                <table class="fin-table" data-test="donor-receipts">
                    <thead>
                        <tr>
                            <th>{{ __('ledger.builder.date') }}</th>
                            <th>{{ __('ledger.donor_report.voucher') }}</th>
                            <th>{{ __('ledger.builder.description') }}</th>
                            <th class="fin-num">{{ __('ledger.donor_report.amount_donor', ['currency' => $currency]) }}</th>
                            <th class="fin-num">USD</th>
                            <th class="fin-num">EUR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['receipts'] as $receipt)
                            <tr wire:key="donor-receipt-{{ $receipt['voucher'] }}">
                                <td class="tabular-nums" dir="ltr">{{ $receipt['date'] }}</td>
                                <td dir="ltr" class="text-start"><a href="{{ route('entries.vouchers', ['voucher' => $receipt['voucher']]) }}" wire:navigate class="font-medium text-primary hover:underline dark:text-brand-text">{{ $receipt['voucher'] }}</a></td>
                                <td class="fin-wrap">{{ $receipt['description'] ?: '—' }}</td>
                                <td class="fin-num"><x-money :amount="$receipt['donor']" /></td>
                                <td class="fin-num"><x-money :amount="$receipt['usd']" /></td>
                                <td class="fin-num">
                                    @if ($receipt['eur'] !== null)
                                        <x-money :amount="$receipt['eur']" />
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="fin-empty">{{ __('ledger.donor_report.no_receipts') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($report['receipts'] !== [])
                        <tfoot>
                            <tr>
                                <td colspan="3">{{ __('ledger.donor_report.total') }}</td>
                                <td class="fin-num"><x-money :amount="$report['received']['donor']" /></td>
                                <td class="fin-num"><x-money :amount="$report['received']['usd']" /></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </x-ui.scroll-table>
        </section>

        <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.donor_report.note', ['currency' => $currency]) }}</p>
    @endif
</div>
