@php
    // The tree comes from the web-owned `gl_accounts` table; a page must still open when it cannot be read.
    try {
        $rows = app(\App\Services\Chart\ChartTree::class)->flat();
    } catch (\Throwable) {
        $rows = [];
    }

    $current = request()->routeIs('chart') ? (string) request()->query('account') : '';

    $groups = \App\Support\Navigation::visibleGroups(auth()->user());
    $chartVisible = isset($groups['chart']);
    unset($groups['chart']);
    $currentScreen = request()->route('screen');
    $currentGroup = collect($groups)->filter(fn (array $screens): bool => in_array($currentScreen, $screens, true))->keys()->first();
    $badges = \App\Support\Navigation::badges(auth()->user());
@endphp

{{-- The main sidebar: the menu groups of the mockup, then the chart of accounts as a searchable tree. The top bar keeps language, theme and the user. --}}
<flux:sidebar
    sticky
    stashable
    aria-label="{{ __('common.app_name') }}"
    data-navigation-sidebar
    class="z-50! w-[min(20rem,100vw)]! gap-0! lg:w-(--sidebar-w,16rem)! border-e border-secondary-soft bg-white p-0! text-secondary-dark lg:top-0! lg:h-dvh! lg:max-h-dvh! dark:border-line dark:bg-surface-1 dark:text-ink"
    x-data="{
        rows: {{ \Illuminate\Support\Js::from($rows) }},
        current: {{ \Illuminate\Support\Js::from($current) }},
        q: '',
        open: {},
        by: {},
        groups: {},
        treeOpen: {{ \Illuminate\Support\Js::from($current !== '' || ! $chartVisible) }},
        currentGroup: {{ \Illuminate\Support\Js::from($currentGroup) }},
        init() {
            this.groups = this.loadGroups() ?? {};
            if (this.currentGroup) this.groups[this.currentGroup] = true;
            this.$watch('groups', () => this.saveGroups());
            this.rows.forEach((row) => { this.by[row.code] = row; });
            const saved = this.load();
            if (saved) this.open = saved; else this.rows.forEach((row) => { if (row.depth === 0) this.open[row.code] = true; });
            for (let parent = this.by[this.current]?.parent; parent; parent = this.by[parent]?.parent) this.open[parent] = true;
            this.$watch('open', () => this.save());
            this.$nextTick(() => this.$el.querySelector('[data-test=chart-tree] [aria-current]')?.scrollIntoView({ block: 'nearest' }));
        },
        get visible() {
            const q = this.q.trim().toLowerCase();
            if (q) {
                const keep = new Set();
                this.rows.forEach((row) => {
                    if (!row.search.includes(q)) return;
                    for (let node = row; node; node = node.parent ? this.by[node.parent] : null) keep.add(node.code);
                });
                return this.rows.filter((row) => keep.has(row.code));
            }
            return this.rows.filter((row) => {
                for (let parent = row.parent; parent; parent = this.by[parent].parent) if (!this.open[parent]) return false;
                return true;
            });
        },
        // Which nodes and menu groups are open is remembered in this browser only; storage can be missing or blocked.
        load() {
            try {
                const saved = JSON.parse(localStorage.getItem('hc.chart-tree.open') ?? 'null');
                if (! saved || typeof saved !== 'object') return null;
                return Object.fromEntries(Object.keys(saved).filter((code) => saved[code] && this.by[code]).map((code) => [code, true]));
            } catch (e) { return null; }
        },
        save() {
            try { localStorage.setItem('hc.chart-tree.open', JSON.stringify(Object.fromEntries(Object.keys(this.open).filter((code) => this.open[code]).map((code) => [code, true])))); } catch (e) {}
        },
        loadGroups() {
            try {
                const saved = JSON.parse(localStorage.getItem('hc.nav.groups') ?? 'null');
                return saved && typeof saved === 'object' ? saved : null;
            } catch (e) { return null; }
        },
        saveGroups() {
            try { localStorage.setItem('hc.nav.groups', JSON.stringify(this.groups)); } catch (e) {}
        },
        expandAll() { this.rows.forEach((row) => { if (row.hasChildren) this.open[row.code] = true; }); },
        collapseAll() { this.open = {}; },
        url(code) { return {{ \Illuminate\Support\Js::from(route('chart')) }} + '?account=' + encodeURIComponent(code); },
    }"
>
    <div class="shell-logo">
        <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 touch-manipulation items-center" data-test="home-link">
            <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-9 w-auto" data-no-dim />
        </a>
        <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="x-mark" :aria-label="__('common.close_menu')" />
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-3">
        <nav class="flex flex-col gap-0.5 pt-3" aria-label="{{ __('common.app_name') }}" data-test="top-menu">
            @foreach ($groups as $group => $screens)
                @php($groupCount = collect($screens)->sum(fn ($slug) => $badges[$slug] ?? 0))
                <section data-test="menu-{{ $group }}" class="flex flex-col gap-0.5">
                    <button type="button" class="nav-group" x-on:click="groups['{{ $group }}'] = ! groups['{{ $group }}']" x-bind:aria-expanded="(!! groups['{{ $group }}']).toString()">
                        <span>{{ __('navigation.groups.'.$group) }}</span>
                        <span class="flex items-center gap-1.5">
                            @if ($groupCount > 0)
                                <span class="nav-badge" data-test="badge-{{ $group }}">{{ $groupCount }}</span>
                            @endif
                            <flux:icon.chevron-down variant="micro" class="size-3.5" x-bind:class="groups['{{ $group }}'] && 'rotate-180'" />
                        </span>
                    </button>
                    <div class="flex flex-col gap-0.5" x-show="groups['{{ $group }}']" @if ($currentGroup !== $group) x-cloak @endif>
                        @foreach ($screens as $screen)
                            <a href="{{ route('screens.show', $screen) }}" wire:navigate class="nav-item" @if ($currentScreen === $screen) aria-current="page" @endif>
                                <span class="min-w-0 truncate">{{ __('navigation.screens.'.$screen) }}</span>
                                @if (($badges[$screen] ?? 0) > 0)
                                    <span class="nav-badge" data-test="badge-{{ $screen }}">{{ $badges[$screen] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </nav>

        @if ($chartVisible)
            <div class="mt-3 flex flex-col gap-2 border-t border-secondary-soft pt-3 dark:border-line">
                <div class="flex items-center justify-between gap-1">
                    <a href="{{ route('chart') }}" wire:navigate class="nav-item min-w-0 flex-1" @if (request()->routeIs('chart') && $current === '') aria-current="page" @endif>
                        <span class="min-w-0 truncate">{{ __('navigation.groups.chart') }}</span>
                        <span class="type-meta opacity-70" dir="ltr">{{ count($rows) }}</span>
                    </a>
                    <button type="button" class="nav-toggle" x-on:click="treeOpen = ! treeOpen" x-bind:aria-expanded="treeOpen.toString()" aria-label="{{ __('chart.tree') }}">
                        <flux:icon.chevron-down variant="micro" class="size-4" x-bind:class="treeOpen && 'rotate-180'" />
                    </button>
                </div>
                <div class="flex flex-col gap-2" x-show="treeOpen" @if ($current === '' && $chartVisible) x-cloak @endif>
                    <input
                        type="search"
                        x-model="q"
                        data-test="tree-search"
                        placeholder="{{ __('chart.search') }}"
                        aria-label="{{ __('chart.search') }}"
                        class="w-full rounded-lg border border-secondary-soft bg-white px-3 py-2 text-sm text-secondary-dark placeholder:text-secondary-light dark:border-line dark:bg-surface-0 dark:text-ink"
                    >
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="ghost" type="button" x-on:click="expandAll()">{{ __('chart.expand') }}</flux:button>
                        <flux:button size="sm" variant="ghost" type="button" x-on:click="collapseAll()">{{ __('chart.collapse') }}</flux:button>
                    </div>
                </div>
            </div>
        @endif

        <nav aria-label="{{ __('chart.tree') }}" data-test="chart-tree" x-show="treeOpen" @if ($current === '' && $chartVisible) x-cloak @endif>
            <template x-for="row in visible" :key="row.code">
                <div
                    class="flex items-center rounded-lg hover:bg-secondary-soft/70 dark:hover:bg-white/[0.06]"
                    :class="current === row.code && 'bg-secondary-soft dark:bg-white/[0.08]'"
                    :style="`padding-inline-start: ${row.depth * 14}px`"
                >
                    <button
                        type="button"
                        class="flex size-8 shrink-0 cursor-pointer items-center justify-center text-secondary-light dark:text-ink-faint"
                        :class="! row.hasChildren && 'invisible'"
                        :aria-expanded="row.hasChildren ? (!! open[row.code]).toString() : null"
                        aria-label="{{ __('chart.toggle') }}"
                        x-on:click="open[row.code] = ! open[row.code]"
                    >
                        <flux:icon.chevron-right variant="micro" class="size-3.5 rtl:-scale-x-100" x-bind:class="open[row.code] && 'rotate-90 rtl:rotate-90'" />
                    </button>

                    <a
                        :href="url(row.code)"
                        wire:navigate
                        class="flex min-h-8 min-w-0 flex-1 items-center gap-2 py-1 pe-2 text-sm"
                        :class="[row.level === 'header' ? 'font-semibold' : 'text-secondary-medium dark:text-ink-muted', current === row.code && 'text-primary! dark:text-brand-text!']"
                        :aria-current="current === row.code ? 'page' : null"
                        :title="row.name"
                    >
                        <span class="size-2 shrink-0 rounded-full" :class="{
                            'bg-sky-500': row.class === 'assets',
                            'bg-amber-500': row.class === 'liabilities',
                            'bg-teal-600': row.class === 'net_assets',
                            'bg-emerald-600': row.class === 'income',
                            'bg-primary': row.class === 'expenses',
                        }"></span>
                        <span class="shrink-0 font-mono text-[13px]" dir="ltr" x-text="row.code"></span>
                        <span class="min-w-0 flex-1 truncate" x-text="row.name"></span>
                    </a>
                </div>
            </template>

            <p x-show="visible.length === 0" x-cloak class="px-2 py-3 text-sm text-secondary-medium dark:text-ink-muted">{{ __('chart.no_match') }}</p>
        </nav>
    </div>

    {{-- Drag (or use the arrow keys on) this edge to resize the sidebar; double-click resets it. --}}
    <div
        x-data="sidebarResize"
        role="separator"
        aria-orientation="vertical"
        aria-label="{{ __('chart.resize') }}"
        tabindex="0"
        data-test="sidebar-resizer"
        class="absolute inset-y-0 -end-1 z-10 hidden w-2 cursor-col-resize touch-none transition-colors hover:bg-primary/30 focus-visible:bg-primary/40 focus-visible:outline-none lg:block"
        :class="dragging && 'bg-primary/40'"
        x-on:pointerdown="start($event)"
        x-on:dblclick="reset()"
        x-on:keydown.arrow-right.prevent="nudge(1)"
        x-on:keydown.arrow-left.prevent="nudge(-1)"
    ></div>
</flux:sidebar>
