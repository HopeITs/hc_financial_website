<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.report-loading />

    <x-ui.page-header :title="__('navigation.screens.'.$screen)" :subtitle="__('ledger.follow.intro.'.$screen)">
        <x-slot:actions>
            <x-ui.button tone="primary" wire:click="export" data-test="export-csv">{{ __('ledger.op.export') }}</x-ui.button>
            <x-ui.button wire:click="exportXlsx" data-test="export-xlsx">{{ __('ledger.follow.export_xlsx') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="report-filters">
        <x-ui.month-filter />
        <x-ui.form-input type="date" wire:model.live="from" :label="__('ledger.op.from')" />
        <x-ui.form-input type="date" wire:model.live="to" :label="__('ledger.op.to')" />
        <x-ui.form-select wire:model.live="city" :label="__('ledger.follow.columns.city')" :placeholder="__('ledger.op.all')">
            @foreach ($cities as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="project" :label="__('ledger.op.columns.project')" :placeholder="__('ledger.op.all')">
            @foreach ($projects as $option)
                <option value="{{ $option->code }}">{{ $option->code }} · {{ $option->name }}</option>
            @endforeach
        </x-ui.form-select>
    </div>

    <x-ui.scroll-table>
        <table class="fin-table" data-test="report-table">
            <thead>
                <tr>
                    @foreach ($data['columns'] as $column)
                        <th @class(['fin-num' => in_array($column['type'], ['money', 'number'], true)])>{{ __('ledger.follow.columns.'.$column['key']) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    <tr @if (($row['late_flag'] ?? '') !== '') class="bg-status-critical-soft" title="{{ __('loans.late_hint') }}" data-late-flag @elseif (($row['negative_flag'] ?? '') !== '') class="bg-status-warn-soft" title="{{ __('ledger.follow.negative_hint') }}" data-negative-flag @endif>
                        @foreach ($data['columns'] as $column)
                            @php $value = $row[$column['key']] ?? null; @endphp
                            @if ($column['type'] === 'money')
                                <td class="fin-num"><x-money :amount="$value" /></td>
                            @elseif ($column['type'] === 'number')
                                <td class="fin-num">{{ $value }}</td>
                            @elseif ($column['type'] === 'date' || $column['type'] === 'ltr')
                                <td><span dir="ltr">{{ $value }}</span></td>
                            @else
                                <td>{{ $value !== '' ? $value : '—' }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($data['columns']) }}" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.follow.empty') }}</td></tr>
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
    <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.follow.note.'.$screen) }}</p>
</div>
