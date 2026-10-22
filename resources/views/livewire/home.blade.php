<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('home.title')" :subtitle="__('home.intro', ['name' => auth()->user()->name])" />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-test="home-totals">
        <x-ui.stat-tile :label="__('home.stat_projects')" :value="number_format($projects->count())" icon="folder" />
        <x-ui.stat-tile :label="__('home.stat_cities')" :value="number_format($projects->pluck('cityName')->unique()->count())" icon="map-pin" />
        <x-ui.stat-tile :label="__('home.stat_usd')" :value="number_format((float) $projects->sum(fn ($p) => (float) $p->usd), 2)" unit="USD" icon="banknotes" tone="good" />
        <x-ui.stat-tile :label="__('home.stat_syp')" :value="number_format((float) $projects->sum(fn ($p) => (float) $p->syp), 2)" unit="SYP" icon="banknotes" />
    </div>

    <div class="flex items-stretch gap-0">
        <section class="min-w-0 flex-1">
            <x-ui.panel :title="__('home.active_projects')" :count="$projects->count()">
                <x-slot:toolbar>
                    <div class="w-full sm:w-56">
                        <x-ui.form-select wire:model.live="cityId" :label="__('home.city')" :placeholder="__('home.all_cities')" data-test="city-filter">
                            <option value="">{{ __('home.all_cities') }}</option>
                            @foreach ($cities as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
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
                            <tr wire:key="project-{{ $project->accountId }}">
                                <td class="font-medium text-secondary-dark dark:text-ink">
                                    <a href="{{ route('projects.show', $project->accountId) }}" wire:navigate class="hover:underline">{{ $project->name }}</a>
                                </td>
                                <td class="text-secondary-medium dark:text-ink-muted">{{ $project->cityName }}</td>
                                <td class="fin-num">
                                    <x-money :amount="$project->syp" currency="SYP" />
                                    @if ((float) $project->sypNew !== 0.0)
                                        <span class="block text-xs text-secondary-medium dark:text-ink-muted" dir="ltr">{{ __('home.new_lira', ['amount' => number_format((float) $project->sypNew, 2)]) }}</span>
                                    @endif
                                </td>
                                <td class="fin-num"><x-money :amount="$project->usd" currency="USD" /></td>
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
</div>
