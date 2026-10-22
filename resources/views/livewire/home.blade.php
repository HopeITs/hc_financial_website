<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('home.title')" :subtitle="__('home.intro', ['name' => auth()->user()->name])" />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-test="home-totals">
        <x-ui.stat-tile :label="__('home.stat_projects')" :value="number_format($projects->count())" icon="folder" />
        <x-ui.stat-tile :label="__('home.stat_cities')" :value="number_format($projects->pluck('cityName')->unique()->count())" icon="map-pin" />
        <x-ui.stat-tile :label="__('home.stat_usd')" :value="number_format((float) $projects->sum(fn ($p) => (float) $p->usd), 2)" unit="USD" icon="banknotes" tone="good" />
        <x-ui.stat-tile :label="__('home.stat_syp')" :value="number_format((float) $projects->sum(fn ($p) => (float) $p->syp), 2)" unit="SYP" icon="banknotes" />
    </div>

    <div class="flex items-stretch gap-0">
        {{-- Side tree: city, program, sub-project, account. The handle on its inline-end edge resizes it; the width is saved per person. --}}
        <aside
            x-data="{
                width: {{ $sidebarWidth }},
                min: {{ $minWidth }},
                max: {{ $maxWidth }},
                dragging: false,
                start(event) {
                    this.dragging = true;
                    event.target.setPointerCapture(event.pointerId);
                },
                move(event) {
                    if (! this.dragging) return;
                    const box = this.$el.getBoundingClientRect();
                    const rtl = document.documentElement.dir === 'rtl';
                    this.width = Math.round(Math.min(this.max, Math.max(this.min, rtl ? box.right - event.clientX : event.clientX - box.left)));
                },
                stop() {
                    if (! this.dragging) return;
                    this.dragging = false;
                    $wire.saveSidebarWidth(this.width);
                },
                key(event) {
                    const rtl = document.documentElement.dir === 'rtl';
                    const grow = event.key === (rtl ? 'ArrowLeft' : 'ArrowRight');
                    const shrink = event.key === (rtl ? 'ArrowRight' : 'ArrowLeft');
                    if (! grow && ! shrink) return;
                    event.preventDefault();
                    this.width = Math.min(this.max, Math.max(this.min, this.width + (grow ? 16 : -16)));
                    $wire.saveSidebarWidth(this.width);
                },
            }"
            x-bind:style="`width: ${width}px`"
            class="relative hidden shrink-0 lg:block"
            data-test="side-tree"
            data-width="{{ $sidebarWidth }}"
        >
            <nav
                aria-label="{{ __('home.side_tree') }}"
                class="h-full overflow-y-auto rounded-xl border border-secondary-soft bg-white p-3 text-sm shadow-card dark:border-line dark:bg-surface-1"
            >
                @forelse ($tree as $city)
                    <details class="group" @if ($cityId === $city['id']) open @endif>
                        <summary class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-1.5 font-medium text-secondary-dark hover:bg-secondary-soft/60 dark:text-ink dark:hover:bg-white/[0.06]">
                            <span class="truncate">{{ $city['name'] }}</span>
                            <button
                                type="button"
                                wire:click.prevent="filterCity({{ $city['id'] }})"
                                class="text-xs text-primary hover:underline dark:text-ink-muted"
                            >
                                {{ $cityId === $city['id'] ? __('home.clear_filter') : __('home.filter') }}
                            </button>
                        </summary>

                        <ul class="ms-3 border-s border-secondary-soft ps-2 dark:border-line">
                            @foreach ($city['programs'] as $program)
                                <li>
                                    <details>
                                        <summary class="cursor-pointer truncate rounded-lg px-2 py-1 text-secondary-dark hover:bg-secondary-soft/60 dark:text-ink dark:hover:bg-white/[0.06]">{{ $program['name'] }}</summary>

                                        <ul class="ms-3 border-s border-secondary-soft ps-2 dark:border-line">
                                            @foreach ($program['subProjects'] as $subProject)
                                                <li>
                                                    <details>
                                                        <summary class="cursor-pointer truncate rounded-lg px-2 py-1 text-secondary-medium hover:bg-secondary-soft/60 dark:text-ink-muted dark:hover:bg-white/[0.06]">{{ $subProject['name'] }}</summary>

                                                        <ul class="ms-3 border-s border-secondary-soft ps-2 dark:border-line">
                                                            @foreach ($subProject['accounts'] as $account)
                                                                <li class="truncate px-2 py-0.5 text-xs text-secondary-medium dark:text-ink-muted">{{ $account }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </details>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @empty
                    <p class="px-2 py-1.5 text-secondary-medium dark:text-ink-muted">{{ __('home.no_tree') }}</p>
                @endforelse
            </nav>

            <div
                role="separator"
                tabindex="0"
                aria-orientation="vertical"
                aria-label="{{ __('home.resize_tree') }}"
                aria-valuemin="{{ $minWidth }}"
                aria-valuemax="{{ $maxWidth }}"
                x-bind:aria-valuenow="width"
                x-on:pointerdown.prevent="start($event)"
                x-on:pointermove="move($event)"
                x-on:pointerup="stop()"
                x-on:pointercancel="stop()"
                x-on:keydown="key($event)"
                x-bind:class="dragging ? 'bg-primary' : 'hover:bg-primary/40'"
                class="absolute inset-y-0 -end-1.5 z-10 w-3 cursor-col-resize touch-none rounded-full bg-transparent bg-clip-content px-[5px] focus-visible:bg-primary/60"
                data-test="side-tree-handle"
            ></div>
        </aside>

        <section class="min-w-0 flex-1 lg:ps-6">
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

                <div class="overflow-x-auto">
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
                </div>
            </x-ui.panel>
        </section>
    </div>
</div>
