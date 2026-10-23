<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.tb')" :subtitle="__('ledger.tb.intro')">
        <x-slot:actions>
            <x-ui.button tone="primary" wire:click="export" data-test="export-csv">{{ __('ledger.tb.export') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-end gap-4 sm:grid-cols-2" data-test="tb-filters">
        <x-ui.form-input type="date" wire:model.live="asAt" :label="__('ledger.tb.as_at')" />
        <x-ui.currency-switch />
    </div>

    <p class="text-sm font-semibold text-secondary-dark dark:text-ink" data-test="tb-check">
        {{ $data['balanced'] ? __('ledger.tb.balanced') : __('ledger.tb.unbalanced', ['difference' => number_format((float) $data['difference'], 2)]) }}
    </p>

    <x-ui.scroll-table>
        <table class="fin-table" data-test="tb-table">
            <thead>
                <tr>
                    <th>{{ __('ledger.tb.account') }}</th>
                    <th>{{ __('ledger.tb.name') }}</th>
                    <th class="fin-num">{{ __('ledger.tb.debit') }} ({{ $unit }})</th>
                    <th class="fin-num">{{ __('ledger.tb.credit') }} ({{ $unit }})</th>
                    <th class="fin-num">{{ __('ledger.tb.net') }} ({{ $unit }})</th>
                </tr>
            </thead>
            @forelse ($data['classes'] as $class)
                <tbody>
                    <tr class="font-semibold" data-test="tb-class-{{ $class['class'] }}">
                        <td colspan="2">{{ __('ledger.class.'.$class['class']) }}</td>
                        <td class="fin-num"><x-money :amount="$class['debit']" /></td>
                        <td class="fin-num"><x-money :amount="$class['credit']" /></td>
                        <td class="fin-num"><x-money :amount="$class['net']" /></td>
                    </tr>
                    @foreach ($class['rows'] as $row)
                        <tr>
                            <td><span dir="ltr">{{ $row['code'] }}</span></td>
                            <td><a href="{{ route('statement', ['account' => $row['code']]) }}" class="underline">{{ app()->getLocale() === 'ar' ? $row['name_ar'] : $row['name_en'] }}</a></td>
                            <td class="fin-num"><x-money :amount="$row['debit']" /></td>
                            <td class="fin-num"><x-money :amount="$row['credit']" /></td>
                            <td class="fin-num"><x-money :amount="$row['net']" /></td>
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody><tr><td colspan="5" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.tb.empty') }}</td></tr></tbody>
            @endforelse
            <tfoot>
                <tr class="font-semibold" data-test="tb-total">
                    <td colspan="2">{{ __('ledger.tb.total') }}</td>
                    <td class="fin-num"><x-money :amount="$data['debit']" /></td>
                    <td class="fin-num"><x-money :amount="$data['credit']" /></td>
                    <td class="fin-num"><x-money :amount="$data['net']" /></td>
                </tr>
            </tfoot>
        </table>
    </x-ui.scroll-table>
    <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.tb.note') }}</p>
</div>
