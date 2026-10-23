<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.'.$screen)" :subtitle="__('master.decision.'.$screen)" />

    <p class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" data-test="reference-note">{{ __('master.read_only') }}</p>

    <div class="grid items-end gap-4 sm:grid-cols-3" data-test="reference-filters">
        <x-ui.form-input wire:model.live.debounce.400ms="search" :label="__('master.search')" />
        @if ($screen === 'log')
            <x-ui.form-input type="date" wire:model.live="from" :label="__('ledger.transfers.effective_from')" />
            <x-ui.form-input type="date" wire:model.live="to" :label="__('vouchers.to')" />
        @endif
    </div>

    <x-ui.scroll-table :paginate="false">
        <table class="fin-table" data-test="reference-table">
            <thead>
                <tr>
                    @foreach ($columns as [$key, $label, $type])
                        <th @class(['fin-num' => $type === 'number'])>{{ __($label) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($page as $row)
                    <tr wire:key="row-{{ $loop->index }}">
                        @foreach ($columns as [$key, $label, $type])
                            @php($value = $row[$key] ?? null)
                            <td @class(['fin-num' => $type === 'number'])>
                                @if ($type === 'yesno')
                                    {{ $value ? __('master.yes') : __('master.no') }}
                                @elseif ($type === 'date' || $type === 'number')
                                    <span dir="ltr">{{ $type === 'date' ? substr((string) $value, 0, 10) : $value }}</span>
                                @else
                                    {{ $value ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="text-secondary-medium dark:text-ink-muted">{{ __('master.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>

    {{ $page->links() }}
</div>
