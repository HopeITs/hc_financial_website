<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.'.$screen)" :subtitle="__('ledger.op.intro.'.$screen)">
        <x-slot:actions>
            <x-ui.button tone="primary" wire:click="export" data-test="export-csv">{{ __('ledger.op.export') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="report-filters">
        <x-ui.form-select wire:model.live="project" :label="__('ledger.op.columns.project')" :placeholder="__('ledger.op.all')">
            @foreach ($projects as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="donor" :label="__('ledger.op.columns.donor')" :placeholder="__('ledger.op.all')">
            @foreach ($donors as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="account" :label="__('ledger.op.account')" :placeholder="__('ledger.op.all')">
            @foreach ($accounts as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->localizedName() }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="lineCurrency" :label="__('ledger.op.line_currency')" :placeholder="__('ledger.op.all')">
            <option value="USD">USD</option>
            <option value="SYP">SYP</option>
            <option value="EUR">EUR</option>
        </x-ui.form-select>
        @if ($usesDates)
            <x-ui.month-filter />
            <x-ui.form-input type="date" wire:model.live="from" :label="__('ledger.op.from')" />
            <x-ui.form-input type="date" wire:model.live="to" :label="__('ledger.op.to')" />
        @endif
    </div>

    @if ($usesCurrency)
        <x-ui.currency-switch />
    @endif

    <x-ui.scroll-table>
        <table class="fin-table" data-test="report-table">
            <thead>
                <tr>
                    @foreach ($data['columns'] as $column)
                        <th @class(['fin-num' => $column['type'] === 'money'])>{{ __('ledger.op.columns.'.$column['key']) }}@if ($usesCurrency && $column['key'] === 'amount') ({{ $unit }})@endif</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    <tr>
                        @foreach ($data['columns'] as $column)
                            @php $value = $row[$column['key']] ?? null; @endphp
                            @if ($column['type'] === 'money')
                                <td class="fin-num"><x-money :amount="$value" /></td>
                            @elseif ($column['type'] === 'voucher')
                                <td><a href="{{ route('entries.vouchers', ['voucher' => $value]) }}" class="underline" dir="ltr">{{ $value }}</a></td>
                            @elseif ($column['type'] === 'kind')
                                <td>{{ __('ledger.op.kind.'.$value) }}</td>
                            @elseif ($column['type'] === 'date' || $column['type'] === 'ltr')
                                <td><span dir="ltr">{{ $value }}</span></td>
                            @else
                                <td>{{ $value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($data['columns']) }}" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.op.empty') }}</td></tr>
                @endforelse
            </tbody>
            @if ($data['totals'] !== [])
                <tfoot>
                    <tr class="font-semibold" data-test="report-total">
                        @foreach ($data['columns'] as $column)
                            @if (isset($data['totals'][$column['key']]))
                                <td class="fin-num"><x-money :amount="$data['totals'][$column['key']]" /></td>
                            @else
                                <td>{{ $loop->first ? __('ledger.op.total') : '' }}</td>
                            @endif
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </x-ui.scroll-table>
    <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.op.note') }}</p>
</div>
