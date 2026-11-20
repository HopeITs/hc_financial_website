<div class="flex flex-col gap-6" data-test="home-overview">
    @if ($liquidity !== null || $rates !== null)
        <div class="grid gap-6 lg:grid-cols-3">
            @if ($liquidity !== null)
                <x-ui.panel :title="__('home.overview.liquidity_title')" :subtitle="__('home.overview.liquidity_subtitle')" :class="$rates !== null ? 'lg:col-span-2' : 'lg:col-span-3'" data-test="home-liquidity">
                    <x-slot:toolbar>
                        <x-ui.button tone="ghost" size="sm" :href="route(\App\Support\Navigation::routeName('treasury'))" wire:navigate icon-trailing="chevron-right">{{ __('home.overview.open') }}</x-ui.button>
                    </x-slot:toolbar>

                    @if ($liquidity['groups'] === [])
                        <x-ui.empty-state icon="banknotes" :heading="__('home.overview.liquidity_empty')" />
                    @else
                        <ul role="list" class="divide-y divide-secondary-soft dark:divide-line">
                            @foreach ($liquidity['groups'] as $group)
                                <li wire:key="liquidity-{{ md5($group['key']) }}" class="flex items-center justify-between gap-4 px-4 py-3">
                                    <span class="flex min-w-0 flex-col">
                                        <span class="truncate text-sm font-medium text-secondary-dark dark:text-ink">{{ $group['label'] }}</span>
                                        <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ trans_choice('home.overview.accounts', $group['accounts'], ['count' => $group['accounts']]) }}</span>
                                    </span>
                                    <x-money :amount="$group['value']" currency="USD" class="shrink-0 text-sm font-semibold" />
                                </li>
                            @endforeach
                            <li class="flex items-center justify-between gap-4 bg-surface-2 px-4 py-3 dark:bg-surface-2">
                                <span class="text-sm font-bold text-secondary-dark dark:text-ink">{{ __('home.overview.liquidity_total') }}</span>
                                <x-money :amount="$liquidity['total_value']" currency="USD" class="shrink-0 text-base font-bold" data-test="liquidity-total" />
                            </li>
                        </ul>
                        @if ($liquidity['missing_rates'] !== [])
                            <p class="border-t border-secondary-soft px-4 py-2 text-xs text-status-warn dark:border-line" data-test="liquidity-missing">{{ __('home.overview.liquidity_missing', ['currencies' => implode(', ', $liquidity['missing_rates'])]) }}</p>
                        @endif
                    @endif
                </x-ui.panel>
            @endif

            @if ($rates !== null)
                <x-ui.panel :title="__('home.overview.rates_title')" :class="$liquidity === null ? 'lg:col-span-3' : ''" data-test="home-rates">
                    <x-slot:toolbar>
                        <x-ui.button tone="ghost" size="sm" :href="route(\App\Support\Navigation::routeName('alloc'))" wire:navigate icon-trailing="chevron-right">{{ __('home.overview.open') }}</x-ui.button>
                    </x-slot:toolbar>

                    <ul role="list" class="divide-y divide-secondary-soft dark:divide-line">
                        @forelse ($rates as $rate)
                            <li wire:key="rate-{{ $rate['currency'] }}" class="flex items-center justify-between gap-4 px-4 py-3" data-test="rate-{{ $rate['currency'] }}">
                                <span class="flex min-w-0 flex-col">
                                    <span class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ $rate['currency'] }} <span class="font-normal text-secondary-medium dark:text-ink-muted">· {{ __('home.overview.rate_'.$rate['kind']) }}</span></span>
                                    <span class="type-meta text-secondary-medium dark:text-ink-muted">
                                        @if ($rate['rate'] === null)
                                            {{ __('home.overview.rate_none') }}
                                        @elseif ($rate['kind'] === 'monthly')
                                            {{ __('home.overview.rate_month', ['month' => $rate['as_of']]) }}
                                        @else
                                            {{ __('home.overview.rate_day', ['day' => $rate['as_of']]) }}
                                        @endif
                                    </span>
                                </span>
                                <span class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="text-base font-bold tabular-nums text-secondary-dark dark:text-ink" dir="ltr">{{ $rate['rate'] ?? '—' }}</span>
                                    @if ($rate['rate'] === null)
                                        <flux:badge size="sm" color="red">{{ __('home.overview.rate_missing') }}</flux:badge>
                                    @elseif ($rate['provisional'])
                                        <flux:badge size="sm" color="amber">{{ __('home.overview.rate_provisional') }}</flux:badge>
                                    @elseif ($rate['stale'])
                                        <flux:badge size="sm" color="amber">{{ __('home.overview.rate_stale') }}</flux:badge>
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li class="px-4 py-3 text-sm text-secondary-medium dark:text-ink-muted">{{ __('home.overview.rates_empty') }}</li>
                        @endforelse
                    </ul>
                    @if ($pendingRates > 0)
                        <a href="{{ route(\App\Support\Navigation::routeName('alloc')) }}" wire:navigate class="block border-t border-secondary-soft px-4 py-2 text-xs font-medium text-status-warn hover:underline dark:border-line" data-test="rates-pending">{{ trans_choice('home.overview.rates_pending', $pendingRates, ['count' => $pendingRates]) }}</a>
                    @endif
                </x-ui.panel>
            @endif
        </div>
    @endif

    @if ($funding !== null)
        <x-ui.panel :title="__('home.overview.funding_title')" :subtitle="$funding['late_count'] > 0 ? trans_choice('home.overview.funding_late', $funding['late_count'], ['count' => $funding['late_count']]) : __('home.overview.funding_subtitle', ['days' => \App\Services\Home\HomeOverview::COMING_DAYS])" :count="count($funding['instalments'])" data-test="home-funding">
            <x-slot:toolbar>
                <x-ui.button tone="ghost" size="sm" :href="route(\App\Support\Navigation::routeName('contracts'))" wire:navigate icon-trailing="chevron-right">{{ __('home.overview.open') }}</x-ui.button>
            </x-slot:toolbar>

            <x-ui.scroll-table>
                <table class="fin-table" data-test="funding-table">
                    <thead>
                        <tr>
                            <th>{{ __('home.overview.agreement') }}</th>
                            <th>{{ __('home.overview.donor') }}</th>
                            <th>{{ __('home.overview.instalment') }}</th>
                            <th>{{ __('home.overview.due') }}</th>
                            <th>{{ __('home.overview.state') }}</th>
                            <th class="fin-num">{{ __('home.overview.remaining') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($funding['instalments'] as $row)
                            <tr wire:key="instalment-{{ $row['contract'] }}-{{ $row['instalment'] }}">
                                <td>
                                    <a href="{{ route('funding.agreements.show', ['contract' => $row['contract'], 'tab' => 'instalments']) }}" wire:navigate class="font-medium text-primary hover:underline dark:text-brand-text">{{ $row['number'] }}</a>
                                    <span class="block max-w-64 truncate text-xs text-secondary-medium dark:text-ink-muted">{{ $row['title'] }}</span>
                                </td>
                                <td class="text-secondary-medium dark:text-ink-muted">{{ $row['donor'] ?: '—' }}</td>
                                <td class="tabular-nums">{{ $row['instalment'] }}</td>
                                <td class="tabular-nums" dir="ltr">{{ $row['due'] }}</td>
                                <td>
                                    @if ($row['late'])
                                        <flux:badge size="sm" color="red" data-test="instalment-late">{{ trans_choice('home.overview.late_days', $row['days'], ['count' => $row['days']]) }}</flux:badge>
                                    @elseif ($row['conditional'])
                                        <flux:badge size="sm" color="zinc">{{ __('home.overview.conditional') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="sky">{{ $row['days'] === 0 ? __('home.overview.due_today') : trans_choice('home.overview.due_in', $row['days'], ['count' => $row['days']]) }}</flux:badge>
                                    @endif
                                    @if ((float) $row['pending'] > 0)
                                        <span class="mt-1 block text-xs text-secondary-medium dark:text-ink-muted">{{ __('home.overview.pending_receipt') }}</span>
                                    @endif
                                </td>
                                <td class="fin-num"><x-money :amount="$row['remaining']" :currency="$row['currency']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state icon="calendar" :heading="__('home.overview.funding_empty', ['days' => \App\Services\Home\HomeOverview::COMING_DAYS])" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.scroll-table>

            @if ($funding['ending'] !== [])
                <div class="border-t border-secondary-soft px-4 py-3 dark:border-line" data-test="funding-ending">
                    <p class="mb-2 text-xs font-semibold text-secondary-dark dark:text-ink">{{ __('home.overview.ending_title', ['days' => \App\Services\Home\HomeOverview::ENDING_DAYS]) }}</p>
                    <ul role="list" class="flex flex-wrap gap-2">
                        @foreach ($funding['ending'] as $ending)
                            <li wire:key="ending-{{ $ending['contract'] }}">
                                <a href="{{ route('funding.agreements.show', ['contract' => $ending['contract']]) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-secondary-soft px-3 py-1.5 text-xs hover:bg-surface-2 dark:border-line dark:hover:bg-white/[0.04]">
                                    <span class="font-medium text-secondary-dark dark:text-ink">{{ $ending['number'] }}</span>
                                    <span class="text-secondary-medium dark:text-ink-muted">{{ __('home.overview.ends_on', ['date' => $ending['ends'], 'days' => $ending['days']]) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-ui.panel>
    @endif
</div>
