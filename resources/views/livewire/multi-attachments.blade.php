<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.multiatt')" :subtitle="__('ledger.multiatt.intro')" />

    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <x-ui.panel class="flex flex-col gap-3 p-4">
        <p class="text-sm">{{ __('ledger.multiatt.naming') }}</p>
        <input type="file" wire:model="files" multiple accept=".pdf,.jpg,.jpeg,.png" class="text-sm" data-test="multiatt-files">
        @error('files*')
            <p class="text-sm text-status-critical">{{ $message }}</p>
        @enderror
    </x-ui.panel>

    @if ($rows !== [])
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="multiatt-rows">
                <thead>
                    <tr>
                        <th>{{ __('ledger.multiatt.file') }}</th>
                        <th>{{ __('ledger.multiatt.voucher') }}</th>
                        <th>{{ __('ledger.multi.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr data-test="multiatt-row-{{ $row['index'] }}">
                            <td>{{ $row['name'] }}</td>
                            <td dir="ltr" class="text-start">{{ $row['voucher'] ?? '—' }}</td>
                            <td class="text-sm">
                                @if ($row['found'])
                                    <span class="text-status-success">{{ __('ledger.multi.ok') }}</span>
                                @else
                                    <span class="text-status-critical">{{ $row['voucher'] === null ? __('ledger.multiatt.no_number') : __('ledger.multiatt.no_voucher') }}</span>
                                @endif
                            </td>
                            <td><button type="button" wire:click="removeFile({{ $row['index'] }})" class="text-status-critical hover:underline">{{ __('ledger.builder.remove_file') }}</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.scroll-table>
    @endif

    <div class="flex items-center gap-3">
        <x-ui.button tone="primary" wire:click="save" :disabled="$rows === [] || $bad > 0" data-test="save-multiatt">{{ __('ledger.multiatt.save', ['count' => count($rows)]) }}</x-ui.button>
        @if ($bad > 0)
            <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('ledger.multi.all_or_nothing') }}</p>
        @endif
    </div>
</div>
