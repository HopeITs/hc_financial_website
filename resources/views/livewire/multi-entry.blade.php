<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.multi')" :subtitle="__('ledger.multi.intro')" />

    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <x-ui.panel class="flex flex-col gap-3 p-4" data-test="multi-help">
        <p class="text-sm">{{ __('ledger.multi.columns') }}</p>
        <p class="text-sm" dir="ltr" data-test="multi-columns"><code>{{ implode(' | ', $columns) }}</code></p>
        <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('ledger.multi.help') }}</p>
    </x-ui.panel>

    <x-ui.form-textarea wire:model.live.debounce.600ms="sheet" :label="__('ledger.multi.paste')" rows="8" dir="ltr" data-test="multi-sheet" />

    @if ($rows !== [])
        <p class="text-sm font-semibold" data-test="multi-summary">
            {{ __('ledger.multi.summary', ['rows' => count($rows), 'bad' => $bad]) }}
            @if ($truncated)
                <span class="text-status-critical">{{ __('ledger.multi.too_many', ['max' => \App\Services\Ledger\OperationSheet::MAX_ROWS]) }}</span>
            @endif
        </p>

        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="multi-rows">
                <thead>
                    <tr>
                        <th>{{ __('ledger.multi.line') }}</th>
                        <th>{{ __('ledger.builder.date') }}</th>
                        <th>{{ __('ledger.entry.kind') }}</th>
                        <th>{{ __('ledger.transfers.project') }}</th>
                        <th>{{ __('ledger.transfers.cash_account') }}</th>
                        <th class="fin-num">{{ __('ledger.multi.amount') }}</th>
                        <th>{{ __('ledger.multi.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr data-test="multi-row-{{ $row['line'] }}">
                            <td>{{ $row['line'] }}</td>
                            <td dir="ltr" class="text-start">{{ $row['operation']['date'] }}</td>
                            <td>{{ $row['operation']['kind'] }}</td>
                            <td dir="ltr" class="text-start">{{ $row['operation']['project'] }}</td>
                            <td dir="ltr" class="text-start">{{ $row['operation']['cash'] }}</td>
                            <td class="fin-num" dir="ltr">{{ $row['operation']['amount'] }}</td>
                            <td class="text-sm">
                                @if ($row['messages'] === [])
                                    <span class="text-status-success">{{ __('ledger.multi.ok') }}</span>
                                @else
                                    @foreach ($row['messages'] as $message)
                                        <p class="text-status-critical">{{ $message }}</p>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.scroll-table>
    @endif

    <div class="flex items-center gap-3">
        <x-ui.button tone="primary" wire:click="save" :disabled="$rows === [] || $bad > 0" data-test="save-multi">{{ __('ledger.multi.save', ['count' => count($rows)]) }}</x-ui.button>
        @if ($bad > 0)
            <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('ledger.multi.all_or_nothing') }}</p>
        @endif
    </div>
</div>
