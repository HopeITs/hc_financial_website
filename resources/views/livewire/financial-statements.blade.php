<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.report-loading />

    <x-ui.page-header :title="__('navigation.screens.statements')" :subtitle="__('ledger.statements.intro')">
        <x-slot:actions>
            <x-ui.button wire:click="export('xlsx')" data-test="export-xlsx">{{ __('ledger.statements.xlsx') }}</x-ui.button>
            <x-ui.button tone="primary" wire:click="export('pdf')" data-test="export-pdf">{{ __('ledger.statements.pdf') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3" data-test="statements-filters">
        <x-ui.form-input type="date" wire:model.live="asAt" :label="__('ledger.statements.as_at')" />
        <x-ui.form-input type="date" wire:model.live="from" :label="__('ledger.statements.from')" />
        <x-ui.form-input type="date" wire:model.live="to" :label="__('ledger.statements.to')" />
    </div>
    <x-ui.currency-switch />
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" wire:model.live="compare" data-test="compare">
        {{ __('ledger.statements.compare') }}
    </label>

    @php
        $position = $data['position'];
        $activities = $data['activities'];
        $compare = $position['sections'][0]['previous'] !== null;
    @endphp

    <p class="text-sm font-semibold text-secondary-dark dark:text-ink" data-test="statements-check">
        {{ $position['balanced'] ? __('ledger.statements.balanced') : __('ledger.statements.unbalanced', ['difference' => number_format((float) $position['difference'], 2)]) }}
    </p>

    <x-net-assets :net="$netAssets" />

    @foreach ([['position', __('ledger.statements.position'), $position['sections'], $position['liabilities_and_net_assets'], __('ledger.statements.liabilities_and_net_assets')], ['activities', __('ledger.statements.activities'), $activities['sections'], $activities['surplus'], __('ledger.statements.surplus')]] as [$key, $heading, $sections, $grand, $grandLabel])
        <section class="flex flex-col gap-3" data-test="statement-{{ $key }}">
            <h2 class="text-xl font-bold">{{ $heading }}</h2>
            <x-ui.scroll-table>
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>{{ __('ledger.statements.line') }}</th>
                            <th class="fin-num">{{ $key === 'position' ? $asAt : $from.' – '.$to }} ({{ $unit }})</th>
                            @if ($compare)
                                <th class="fin-num">{{ __('ledger.statements.compare') }} ({{ $unit }})</th>
                            @endif
                        </tr>
                    </thead>
                    @foreach ($sections as $section)
                        <tbody>
                            <tr class="font-semibold" data-test="{{ $key }}-{{ $section['key'] }}">
                                <td colspan="{{ $compare ? 3 : 2 }}">{{ __('ledger.class.'.$section['key']) }}</td>
                            </tr>
                            @forelse ($section['lines'] as $line)
                                <tr>
                                    <td>{{ $this->lineLabel($line['label']) }}</td>
                                    <td class="fin-num"><x-money :amount="$line['current']" /></td>
                                    @if ($compare)
                                        <td class="fin-num"><x-money :amount="$line['previous']" /></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $compare ? 3 : 2 }}" class="text-secondary-medium dark:text-ink-muted">{{ __('ledger.statements.empty') }}</td></tr>
                            @endforelse
                            <tr class="font-semibold">
                                <td>{{ __('ledger.statements.total') }} {{ __('ledger.class.'.$section['key']) }}</td>
                                <td class="fin-num"><x-money :amount="$section['current']" /></td>
                                @if ($compare)
                                    <td class="fin-num"><x-money :amount="$section['previous']" /></td>
                                @endif
                            </tr>
                        </tbody>
                    @endforeach
                    <tfoot>
                        <tr class="font-semibold" data-test="{{ $key }}-total">
                            <td>{{ $grandLabel }}</td>
                            <td class="fin-num"><x-money :amount="$grand['current']" /></td>
                            @if ($compare)
                                <td class="fin-num"><x-money :amount="$grand['previous']" /></td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
            </x-ui.scroll-table>
        </section>
    @endforeach

    <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.statements.note') }}</p>
</div>
