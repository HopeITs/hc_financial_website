@php
    // The tree comes from the web-owned `gl_accounts` table; a page must still open when it cannot be read.
    try {
        $rows = app(\App\Services\Chart\ChartTree::class)->flat();
    } catch (\Throwable) {
        $rows = [];
    }

    $current = request()->routeIs('chart') ? (string) request()->query('account') : '';
@endphp

{{-- The main sidebar: only the chart of accounts, as a tree. The rest of the menu is in the top bar. --}}
<flux:sidebar
    sticky
    stashable
    aria-label="{{ __('chart.tree') }}"
    data-navigation-sidebar
    class="z-50! w-[min(20rem,100vw)]! gap-0! lg:w-(--sidebar-w,20rem)! border-e border-secondary-soft bg-white p-0! text-secondary-dark lg:top-16! lg:h-[calc(100dvh-4rem)]! lg:max-h-[calc(100dvh-4rem)]! dark:border-line dark:bg-surface-1 dark:text-ink"
    x-data="{
        rows: {{ \Illuminate\Support\Js::from($rows) }},
        current: {{ \Illuminate\Support\Js::from($current) }},
        q: '',
        open: {},
        by: {},
        init() {
            this.rows.forEach((row) => { this.by[row.code] = row; if (row.depth === 0) this.open[row.code] = true; });
            for (let parent = this.by[this.current]?.parent; parent; parent = this.by[parent]?.parent) this.open[parent] = true;
            this.$nextTick(() => this.$el.querySelector('[aria-current]')?.scrollIntoView({ block: 'nearest' }));
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
        expandAll() { this.rows.forEach((row) => { if (row.hasChildren) this.open[row.code] = true; }); },
        collapseAll() { this.open = {}; },
        url(code) { return {{ \Illuminate\Support\Js::from(route('chart')) }} + '?account=' + encodeURIComponent(code); },
    }"
>
    <div class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-secondary-soft px-4 dark:border-line">
        <p class="text-base font-bold">{{ __('chart.tree') }}</p>
        <span class="type-meta text-secondary-medium dark:text-ink-muted" dir="ltr">{{ count($rows) }}</span>
        <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="x-mark" />
    </div>

    <div class="flex shrink-0 flex-col gap-2 border-b border-secondary-soft p-3 dark:border-line">
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

    <nav class="min-h-0 flex-1 overflow-y-auto p-2" aria-label="{{ __('chart.tree') }}" data-test="chart-tree">
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
                    <span class="shrink-0 tabular-nums" dir="ltr" x-text="row.code"></span>
                    <span class="min-w-0 flex-1 truncate" x-text="row.name"></span>
                </a>
            </div>
        </template>

        <p x-show="visible.length === 0" x-cloak class="px-2 py-3 text-sm text-secondary-medium dark:text-ink-muted">{{ __('chart.no_match') }}</p>
    </nav>

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
