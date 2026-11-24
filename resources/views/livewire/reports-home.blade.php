<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="reports-home">
    <x-ui.page-header :title="__('navigation.screens.reports')" :subtitle="__('reports_home.intro')" />

    @forelse ($sections as $section => $reports)
        <section class="flex flex-col gap-3" data-test="reports-{{ $section }}">
            <div>
                <h2 class="font-semibold text-secondary-dark dark:text-ink">{{ __('reports_home.sections.'.$section.'.title') }}</h2>
                <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('reports_home.sections.'.$section.'.hint') }}</p>
            </div>
            <ul role="list" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($reports as $slug => $report)
                    <li wire:key="report-{{ $slug }}">
                        <a href="{{ $report['url'] }}" wire:navigate class="flex h-full items-start gap-3 rounded-xl border border-secondary-soft bg-white px-4 py-3 shadow-card hover:border-primary/40 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-primary dark:border-transparent dark:bg-surface-1 dark:shadow-raised dark:hover:bg-white/[0.04]" data-test="report-{{ $slug }}">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary-soft text-primary dark:bg-surface-2 dark:text-brand-text" aria-hidden="true">
                                <flux:icon :name="$report['icon']" variant="mini" class="size-5" />
                            </span>
                            <span class="flex min-w-0 flex-col gap-1">
                                <span class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('navigation.screens.'.$slug) }}</span>
                                @if (\Illuminate\Support\Facades\Lang::has('navigation.descriptions.'.$slug))
                                    <span class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('navigation.descriptions.'.$slug) }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <x-ui.empty-state icon="chart-bar" :heading="__('reports_home.none')" />
    @endforelse
</div>
