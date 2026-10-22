<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.chart')" :subtitle="__('chart.intro')" :count="$total" />

    @if ($account === null)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-test="chart-classes">
            @foreach ($roots as $root)
                <a href="{{ route('chart', ['account' => $root->code]) }}" wire:navigate class="block rounded-xl border border-secondary-soft bg-white p-4 shadow-card hover:bg-secondary-soft/40 dark:border-transparent dark:bg-surface-1 dark:hover:bg-white/[0.04]">
                    <p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.classes.'.$root->class) }}</p>
                    <p class="mt-2 text-lg font-semibold text-secondary-dark dark:text-ink"><span dir="ltr">{{ $root->code }}</span> · {{ $root->localizedName() }}</p>
                    <p class="type-meta mt-1 text-secondary-medium dark:text-ink-muted">{{ __('chart.posting_accounts', ['count' => $postingCounts[$root->class] ?? 0]) }}</p>
                </a>
            @endforeach
        </div>

        <x-ui.panel class="p-4">
            <p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.tags_title') }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach (array_keys(__('chart.tags')) as $tag)
                    <flux:badge size="sm" color="sky">{{ __('chart.tags.'.$tag) }}</flux:badge>
                @endforeach
            </div>
            <p class="type-meta mt-2 text-secondary-medium dark:text-ink-muted">{{ __('chart.tags_note') }}</p>
        </x-ui.panel>
    @else
        <nav aria-label="breadcrumb" class="flex flex-wrap items-center gap-1 text-sm text-secondary-medium dark:text-ink-muted" data-test="chart-path">
            <a href="{{ route('chart') }}" wire:navigate class="hover:underline">{{ __('chart.home') }}</a>
            @foreach ($path as $step)
                <span aria-hidden="true" class="rtl:-scale-x-100">›</span>
                <a href="{{ route('chart', ['account' => $step->code]) }}" wire:navigate class="hover:underline"><span dir="ltr">{{ $step->code }}</span> {{ $step->localizedName() }}</a>
            @endforeach
        </nav>

        <x-ui.panel class="p-5" data-test="chart-account">
            <p class="type-meta text-secondary-medium dark:text-ink-muted"><span dir="ltr">{{ $account->code }}</span> · {{ __('chart.classes.'.$account->class) }} · {{ __('chart.levels.'.$account->level) }}</p>
            <h2 class="mt-1 text-2xl font-semibold text-secondary-dark dark:text-ink">{{ $account->name_en }}</h2>
            <p dir="rtl" class="mt-0.5 text-base text-secondary-medium dark:text-ink-muted">{{ $account->name_ar }}</p>

            @if ($account->usage_note)
                <div class="mt-4">
                    <p class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.how_to_use') }}</p>
                    <p class="mt-1 text-sm text-secondary-dark dark:text-ink">{{ $account->usage_note }}</p>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="type-label text-secondary-medium dark:text-ink-muted">{{ __('chart.tags_asked') }}</span>
                @forelse ($account->tagList() as $tag)
                    <flux:badge size="sm" color="sky">{{ __('chart.tags.'.$tag) }}</flux:badge>
                @empty
                    <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ $account->isHeader() ? __('chart.no_tags') : '—' }}</span>
                @endforelse
            </div>
        </x-ui.panel>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-tile :label="__('chart.normal_side')" :value="__('chart.sides.'.$account->normal_side)" />
            <x-ui.stat-tile :label="__('chart.currency')" :value="$account->currency === 'Any' ? __('chart.any_currency') : ($account->currency ?: '—')" />
            <x-ui.stat-tile :label="__('chart.city')" :value="$account->city ?: '—'" />
            <x-ui.stat-tile :label="__('chart.cash_account')" :value="$account->is_cash ? __('chart.yes') : __('chart.no')" />
        </div>

        @if ($account->statement_line)
            <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('chart.statement_line') }}: <span class="text-secondary-dark dark:text-ink">{{ $account->statement_line }}</span></p>
        @endif

        @if ($children->isNotEmpty())
            <div>
                <h3 class="mb-2 text-base font-semibold text-secondary-dark dark:text-ink">{{ __('chart.below') }}</h3>
                <div class="fin-table-wrap">
                    <table class="fin-table" data-test="chart-children">
                        <thead>
                            <tr>
                                <th>{{ __('chart.code') }}</th>
                                <th>{{ __('chart.name') }}</th>
                                <th>{{ __('chart.level') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($children as $child)
                                <tr>
                                    <td dir="ltr" class="text-start"><a href="{{ route('chart', ['account' => $child->code]) }}" wire:navigate class="font-medium text-primary hover:underline dark:text-brand-text">{{ $child->code }}</a></td>
                                    <td>{{ $child->localizedName() }}</td>
                                    <td>{{ __('chart.levels.'.$child->level) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
