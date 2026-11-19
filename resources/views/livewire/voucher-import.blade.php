<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="voucher-import">
    <x-ui.page-header :title="__('navigation.screens.multi')" :subtitle="__('vouchers.excel.intro')">
        @if ($canCreate)
            <x-slot:actions>
                <x-ui.button icon="document-arrow-down" wire:click="template" data-test="download-template">{{ __('vouchers.excel.download_template') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($problems !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="problems">
            @foreach ($problems as $problem)
                <p>{{ $problem }}</p>
            @endforeach
        </div>
    @endif

    @if ($canCreate)
        <x-ui.panel :title="__('vouchers.excel.steps_title')">
            <ol class="list-decimal space-y-1 p-4 ps-9 text-sm text-secondary-dark dark:text-ink">
                <li>{{ __('vouchers.excel.step_template') }}</li>
                <li>{{ __('vouchers.excel.step_fill') }}</li>
                <li>{{ __('vouchers.excel.step_upload') }}</li>
                <li>{{ __('vouchers.excel.step_save') }}</li>
            </ol>
            <div class="flex flex-col gap-2 border-t border-secondary-soft p-4 dark:border-line">
                <input type="file" wire:model="upload" accept=".xlsx" class="text-sm" aria-label="{{ __('vouchers.excel.upload') }}" data-test="upload">
                @error('upload')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
                <div wire:loading wire:target="upload" class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('vouchers.excel.reading') }}</div>
            </div>
        </x-ui.panel>
    @endif

    @if ($vouchers !== [])
        <x-ui.panel :title="__('vouchers.excel.preview_title', ['file' => $fileName])" :subtitle="$broken === 0 ? __('vouchers.excel.all_good', ['count' => count($vouchers)]) : trans_choice('vouchers.excel.some_broken', $broken, ['count' => $broken])">
            <x-slot:toolbar>
                @if ($broken === 0)
                    <flux:badge size="sm" color="green">{{ __('vouchers.excel.ready') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="red">{{ trans_choice('vouchers.excel.to_fix', $broken, ['count' => $broken]) }}</flux:badge>
                @endif
            </x-slot:toolbar>
            <div class="fin-table-wrap">
                <table class="fin-table" data-test="preview">
                    <thead>
                        <tr>
                            <th>{{ __('vouchers.excel.col.voucher') }}</th>
                            <th>{{ __('vouchers.excel.col.type') }}</th>
                            <th>{{ __('vouchers.excel.col.date') }}</th>
                            <th>{{ __('vouchers.excel.col.description') }}</th>
                            <th class="fin-num">{{ __('vouchers.excel.lines') }}</th>
                            <th class="fin-num">{{ __('vouchers.excel.col.debit_usd') }}</th>
                            <th class="fin-num">{{ __('vouchers.excel.col.credit_usd') }}</th>
                            <th>{{ __('vouchers.excel.check') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vouchers as $voucher)
                            <tr wire:key="import-{{ md5($voucher['ref']) }}" data-test="import-{{ $voucher['ref'] }}">
                                <td dir="ltr" class="text-start">{{ $voucher['ref'] }}</td>
                                <td dir="ltr">{{ $voucher['type'] }}</td>
                                <td dir="ltr" class="text-start">{{ $voucher['date'] }}</td>
                                <td>{{ $voucher['description'] }}</td>
                                <td class="fin-num">{{ $voucher['lines'] }}</td>
                                <td class="fin-num"><x-money :amount="$voucher['debit']" /></td>
                                <td class="fin-num"><x-money :amount="$voucher['credit']" /></td>
                                <td>
                                    @if ($voucher['problems'] === [])
                                        <flux:badge size="sm" color="green">{{ __('vouchers.excel.ok') }}</flux:badge>
                                    @else
                                        <ul class="list-disc ps-4 text-xs text-status-critical">
                                            @foreach ($voucher['problems'] as $problem)
                                                <li>{{ $problem }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center gap-3 border-t border-secondary-soft p-4 dark:border-line">
                <x-ui.button tone="primary" icon="check" wire:click="save" :disabled="$broken > 0" data-test="save-import">{{ trans_choice('vouchers.excel.save', count($vouchers), ['count' => count($vouchers)]) }}</x-ui.button>
                <x-ui.button wire:click="clear" data-test="clear-import">{{ __('vouchers.excel.clear') }}</x-ui.button>
                @if ($broken > 0)
                    <span class="text-sm text-status-critical">{{ __('vouchers.excel.fix_first') }}</span>
                @endif
            </div>
        </x-ui.panel>
    @endif
</div>
