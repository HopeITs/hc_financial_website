<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.report-loading />

    <x-ui.page-header :title="__('home.title')" :subtitle="__('home.intro', ['name' => auth()->user()->name])" />

    <livewire:home-waiting />

    @if ($actions !== [])
        <nav aria-label="{{ __('home.quick.title') }}" class="flex flex-col gap-2" data-test="home-quick">
            <h2 class="text-sm font-semibold text-secondary-medium dark:text-ink-muted">{{ __('home.quick.title') }}</h2>
            <ul role="list" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                @foreach ($actions as $action)
                    <li wire:key="quick-{{ $action['slug'] }}">
                        <a href="{{ $action['url'] }}" wire:navigate class="group flex h-full items-center gap-3 rounded-xl border border-secondary-soft bg-white px-4 py-3 shadow-card hover:border-primary/40 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-primary dark:border-transparent dark:bg-surface-1 dark:shadow-raised dark:hover:bg-white/[0.04]" data-test="quick-{{ $action['slug'] }}">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary-soft text-primary dark:bg-surface-2 dark:text-brand-text" aria-hidden="true">
                                <flux:icon :name="$action['icon']" variant="mini" class="size-5" />
                            </span>
                            <span class="min-w-0 text-sm font-medium text-secondary-dark dark:text-ink">{{ __('navigation.screens.'.$action['slug']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <livewire:home-overview />

    @if ($showMoney)
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4" data-test="home-totals">
        <x-ui.stat-tile :label="__('home.stat_projects')" :value="number_format($projects->count())" icon="folder" />
        <x-ui.stat-tile :label="__('home.stat_cities')" :value="number_format($projects->pluck('city')->filter()->unique()->count())" icon="map-pin" />
        <x-ui.stat-tile :label="__('home.stat_usd')" :value="number_format((float) $projects->sum(fn ($p) => (float) $p->usd), 2)" unit="USD" icon="banknotes" tone="good" />
        <x-ui.stat-tile :label="__('home.stat_syp')" :value="number_format((float) $projects->sum(fn ($p) => (float) $p->syp), 2)" unit="SYP" icon="banknotes" />
    </div>

    <div class="flex items-stretch gap-0">
        <section class="min-w-0 flex-1">
            <x-ui.panel :title="__('home.active_projects')" :count="$projects->count()">
                <x-slot:toolbar>
                    <div class="w-full sm:w-56">
                        <x-ui.form-select wire:model.live="city" :label="__('home.city')" :placeholder="__('home.all_cities')" data-test="city-filter">
                            <option value="">{{ __('home.all_cities') }}</option>
                            @foreach ($cities as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </x-ui.form-select>
                    </div>
                </x-slot:toolbar>

                <x-ui.scroll-table>
                <table class="fin-table" data-test="projects-table">
                    <thead>
                        <tr>
                            <th>{{ __('home.project') }}</th>
                            <th>{{ __('home.city') }}</th>
                            <th class="fin-num">{{ __('home.balance_syp') }}</th>
                            <th class="fin-num">{{ __('home.balance_usd') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($projects as $project)
                            <tr wire:key="project-{{ $project->code }}">
                                <td class="font-medium text-secondary-dark dark:text-ink">
                                    {{ $project->name }}
                                </td>
                                <td class="text-secondary-medium dark:text-ink-muted">{{ $project->city }}</td>
                                <td class="fin-num"><x-money :amount="$project->syp" currency="SYP" /></td>
                                <td class="fin-num">
                                    <x-money :amount="$project->usd" currency="USD" />
                                    @if ((float) $project->otherUsd !== 0.0)
                                        <span class="block text-xs text-secondary-medium dark:text-ink-muted">{{ __('home.other_currency') }} <x-money :amount="$project->otherUsd" currency="USD" /></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"><x-ui.empty-state icon="folder" :heading="__('home.no_projects')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </x-ui.scroll-table>
            </x-ui.panel>
        </section>
    </div>
    @endif
</div>
