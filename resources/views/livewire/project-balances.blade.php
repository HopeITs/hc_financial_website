<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.projbal')" :subtitle="__('ledger.projbal.intro')" />

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3" data-test="projbal-filters">
        <x-ui.form-select wire:model.live="project" :label="__('ledger.statement.project')" :placeholder="__('ledger.statement.all_projects')">
            @foreach ($projects as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
            @endforeach
        </x-ui.form-select>
        <div class="sm:col-span-2 lg:col-span-2"><x-ui.currency-switch /></div>
    </div>

    <x-ui.scroll-table>
        <table class="fin-table" data-test="projbal-table">
            <thead>
                <tr>
                    <th>{{ __('ledger.statement.project') }}</th>
                    <th class="fin-num">{{ __('ledger.projbal.income') }} ({{ $unit }})</th>
                    <th class="fin-num">{{ __('ledger.projbal.expenses') }} ({{ $unit }})</th>
                    <th class="fin-num">{{ __('ledger.projbal.result') }} ({{ $unit }})</th>
                    <th class="fin-num">{{ __('ledger.projbal.cash') }} ({{ $unit }})</th>
                    <th class="fin-num">{{ __('ledger.projbal.restricted') }} ({{ $unit }})</th>
                    <th class="fin-num">{{ __('ledger.projbal.donor_view') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    <tr data-test="projbal-row-{{ $row['code'] }}">
                        <td><span dir="ltr">{{ $row['code'] }}</span> · {{ $row['name'] }}</td>
                        <td class="fin-num"><x-money :amount="$row['income']" /></td>
                        <td class="fin-num"><x-money :amount="$row['expenses']" /></td>
                        <td class="fin-num"><x-money :amount="$row['result']" /></td>
                        <td class="fin-num"><x-money :amount="$row['cash']" /></td>
                        <td class="fin-num"><x-money :amount="$row['restricted']" /></td>
                        <td class="fin-num">
                            @if ($row['donor_currency'] !== 'USD')
                                <x-money :amount="$row['donor_income']" :currency="$row['donor_currency']" /> /
                                <x-money :amount="$row['donor_expenses']" :currency="$row['donor_currency']" />
                            @else
                                <span class="text-secondary-medium dark:text-ink-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.projbal.empty') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold" data-test="projbal-total">
                    <td>{{ __('ledger.tb.total') }}</td>
                    <td class="fin-num"><x-money :amount="$data['totals']['income']" /></td>
                    <td class="fin-num"><x-money :amount="$data['totals']['expenses']" /></td>
                    <td class="fin-num"><x-money :amount="$data['totals']['result']" /></td>
                    <td class="fin-num"><x-money :amount="$data['totals']['cash']" /></td>
                    <td class="fin-num"><x-money :amount="$data['totals']['restricted']" /></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </x-ui.scroll-table>

    <section class="flex flex-col gap-3" data-test="projbal-locations">
        <h2 class="type-heading">{{ __('ledger.projbal.where') }}</h2>
        <x-ui.scroll-table>
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>{{ __('ledger.statement.project') }}</th>
                        <th>{{ __('ledger.tb.account') }}</th>
                        <th>{{ __('ledger.projbal.currency') }}</th>
                        <th class="fin-num">{{ __('ledger.projbal.amount') }}</th>
                        <th class="fin-num">{{ __('ledger.projbal.usd_value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td><span dir="ltr">{{ $location['project_code'] }}</span> · {{ $location['project_name'] }}</td>
                            <td><span dir="ltr">{{ $location['account_code'] }}</span> · {{ app()->getLocale() === 'ar' ? $location['account_name_ar'] : $location['account_name_en'] }}</td>
                            <td><span dir="ltr">{{ $location['currency'] }}</span></td>
                            <td class="fin-num"><x-money :amount="$location['amount']" /></td>
                            <td class="fin-num"><x-money :amount="$location['usd']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.projbal.no_cash') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </section>

    @if ($selected)
        <section class="flex flex-col gap-3" data-test="projbal-budget">
            <h2 class="type-heading">{{ __('ledger.projbal.budget_vs_actual') }}</h2>
            <x-ui.scroll-table>
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>{{ __('ledger.projbal.line') }}</th>
                            <th>{{ __('ledger.projbal.currency') }}</th>
                            <th class="fin-num">{{ __('ledger.projbal.budget') }}</th>
                            <th class="fin-num">{{ __('ledger.projbal.actual') }}</th>
                            <th class="fin-num">{{ __('ledger.projbal.remaining') }}</th>
                            <th class="fin-num">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($budget as $line)
                            <tr data-test="projbal-line-{{ $line['key'] }}">
                                <td>{{ $line['name'] }}</td>
                                <td><span dir="ltr">{{ $line['currency'] }}</span></td>
                                <td class="fin-num"><x-money :amount="$line['budget']" /></td>
                                <td class="fin-num"><x-money :amount="$line['actual']" /></td>
                                <td class="fin-num"><x-money :amount="$line['remaining']" /></td>
                                <td class="fin-num"><span dir="ltr">{{ $line['percent'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.projbal.no_budget') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </section>
    @endif

    <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.projbal.note') }}</p>
</div>
