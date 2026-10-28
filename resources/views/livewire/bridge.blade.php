<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.'.$screen)" :subtitle="__('navigation.descriptions.'.$screen)" />

    @if ($screen === 'decisions')
        @php($tones = ['decided' => 'green', 'open' => 'amber', 'default' => 'blue', 'closed' => 'zinc'])

        <p class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" data-test="bridge-note">
            {{ __('bridge.decisions.intro') }} <code dir="ltr">{{ \App\Services\Bridge\DecisionsLog::FILE }}</code>. {{ __('bridge.decisions.language_note') }}
        </p>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap gap-2" data-test="bridge-summary">
                <flux:badge color="green">{{ __('bridge.decisions.decided', ['count' => $summary['decided']]) }}</flux:badge>
                <flux:badge color="amber">{{ __('bridge.decisions.open', ['count' => $summary['open']]) }}</flux:badge>
                @if ($summary['other'] > 0)
                    <flux:badge color="zinc">{{ __('bridge.decisions.other', ['count' => $summary['other']]) }}</flux:badge>
                @endif
            </div>
            <div class="w-full sm:w-72">
                <x-ui.form-input wire:model.live.debounce.300ms="search" :label="__('bridge.decisions.search')" data-test="bridge-search" />
            </div>
        </div>

        @forelse ($sections as $section)
            <section class="flex flex-col gap-3" dir="ltr" lang="en" wire:key="section-{{ $loop->index }}" data-test="bridge-section">
                <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{!! $section['title_html'] !!}</h2>

                @foreach ($section['blocks'] as $block)
                    @if ($block['type'] === 'table')
                        <x-ui.scroll-table :paginate="false">
                            <table class="fin-table">
                                <thead>
                                    <tr>
                                        @foreach ($block['head'] as $head)
                                            <th>{{ $head }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($block['rows'] as $row)
                                        <tr>
                                            @foreach ($row as $index => $cell)
                                                <td @class(['whitespace-nowrap font-semibold' => $index === 0, 'min-w-64' => $index === 1])>
                                                    @if ($index === $block['status'] && ($tone = \App\Services\Bridge\DecisionsLog::statusTone($cell['text'])) !== null)
                                                        <flux:badge size="sm" :color="$tones[$tone]">{!! $cell['html'] !!}</flux:badge>
                                                    @else
                                                        {!! $cell['html'] !!}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-ui.scroll-table>
                    @elseif ($block['type'] === 'text')
                        <p class="max-w-4xl text-sm text-secondary-medium dark:text-ink-muted">{!! $block['html'] !!}</p>
                    @else
                        <ul class="max-w-4xl list-disc ps-5 text-sm text-secondary-medium dark:text-ink-muted">
                            @foreach ($block['items'] as $item)
                                <li>{!! $item !!}</li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </section>
        @empty
            <x-ui.empty-state :heading="$search === '' ? __('bridge.decisions.empty') : __('bridge.decisions.no_match')" data-test="bridge-empty" />
        @endforelse
    @elseif ($screen === 'mapping')
        <p class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" data-test="bridge-note">{{ __('bridge.mapping.intro') }}</p>

        @foreach (['concepts' => ['col_legacy', 'col_ledger'], 'operations' => ['col_letter', 'col_operation', 'col_voucher', 'col_lines'], 'statuses' => ['col_state', 'col_becomes']] as $table => $columns)
            <section class="flex flex-col gap-3" data-test="mapping-{{ $table }}">
                <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('bridge.mapping.'.$table.'_title') }}</h2>
                <x-ui.scroll-table :paginate="false">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th>{{ __('bridge.mapping.'.$column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ((array) __('bridge.mapping.'.$table) as $row)
                                <tr>
                                    @foreach ($row as $index => $value)
                                        <td @class(['whitespace-nowrap font-semibold' => $index === 0])>{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.scroll-table>
            </section>
        @endforeach
    @else
        @php($states = collect($rules)->countBy('state'))
        @php($colors = [\App\Services\Bridge\PostingRuleCatalogue::LIVE => 'green', \App\Services\Bridge\PostingRuleCatalogue::CODE_ONLY => 'amber', \App\Services\Bridge\PostingRuleCatalogue::DESCRIBED => 'zinc'])

        <p class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" data-test="bridge-note">{{ __('bridge.rules.intro') }}</p>

        <div class="flex flex-wrap gap-2" data-test="bridge-summary">
            @foreach ($colors as $state => $color)
                <flux:badge :color="$color">{{ __('bridge.rules.count.'.$state, ['count' => $states[$state] ?? 0]) }}</flux:badge>
            @endforeach
        </div>

        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="rules-table">
                <thead>
                    <tr>
                        <th class="fin-num">#</th>
                        <th>{{ __('bridge.rules.col_rule') }}</th>
                        <th>{{ __('bridge.rules.col_state') }}</th>
                        <th>{{ __('bridge.rules.col_where') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rules as $rule)
                        <tr wire:key="rule-{{ $rule['number'] }}" data-test="rule-{{ $rule['number'] }}">
                            <td class="fin-num font-semibold">{{ $rule['number'] }}</td>
                            <td class="min-w-72">
                                <p class="font-semibold">{{ __('bridge.rules.items.'.$rule['number'].'.title') }}</p>
                                <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('bridge.rules.items.'.$rule['number'].'.detail') }}</p>
                            </td>
                            <td><flux:badge size="sm" :color="$colors[$rule['state']]">{{ __('bridge.rules.state.'.$rule['state']) }}</flux:badge></td>
                            <td class="whitespace-nowrap" title="{{ $rule['class'] }}">{{ $rule['screen'] !== null ? __('navigation.screens.'.$rule['screen']) : __('bridge.rules.nowhere') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.scroll-table>
    @endif
</div>
