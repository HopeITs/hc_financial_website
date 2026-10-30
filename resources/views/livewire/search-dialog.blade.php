@php
    // Ids of this window's input and results, unique per instance (the teleported part sits outside the component).
    $uid = 'hc-search-'.$this->getId();
    $icons = ['vouchers' => 'document-text', 'accounts' => 'book-open', 'projects' => 'folder', 'screens' => 'squares-2x2'];
@endphp

<div
    x-data="{
        open: false,
        active: 0,
        show() { this.open = true; this.active = 0; this.$nextTick(() => requestAnimationFrame(() => document.getElementById('{{ $uid }}-input')?.focus())) },
        hide() { this.open = false },
        results() { return [...(document.getElementById('{{ $uid }}-results')?.querySelectorAll('[data-result]') ?? [])] },
        key(event) {
            if (event.key === 'ArrowDown') { event.preventDefault(); this.move(1) }
            else if (event.key === 'ArrowUp') { event.preventDefault(); this.move(-1) }
            else if (event.key === 'Enter') { event.preventDefault(); this.go() }
        },
        move(step) {
            const all = this.results();
            if (! all.length) return;
            this.active = (this.active + step + all.length) % all.length;
            all[this.active].scrollIntoView({ block: 'nearest' });
        },
        go() { this.results()[this.active]?.click() },
    }"
    x-on:hc-search-open.window="show()"
    data-test="global-search"
>
    <button
        type="button"
        x-on:click="show()"
        class="flex h-8 items-center gap-2 rounded-lg border border-secondary-soft bg-white px-2 text-sm text-secondary-medium hover:border-secondary-light hover:text-secondary-dark md:w-44 md:px-2.5 dark:border-line dark:bg-surface-2 dark:text-ink-muted dark:hover:text-ink"
        aria-label="{{ __('search.open') }}"
        aria-keyshortcuts="Control+K"
        data-test="search-button"
    >
        <flux:icon.magnifying-glass variant="micro" class="size-4 shrink-0" />
        <span class="hidden flex-1 text-start md:inline">{{ __('search.button') }}</span>
        <span class="hidden items-center gap-0.5 md:flex" dir="ltr" aria-hidden="true"><x-ui.kbd class="h-5! min-w-5! text-[11px]!">Ctrl</x-ui.kbd><x-ui.kbd class="h-5! min-w-5! text-[11px]!">K</x-ui.kbd></span>
    </button>

    @teleport('body')
        <div
            x-show="open"
            x-cloak
            x-on:click.self="hide()"
            x-on:keydown.escape.prevent.stop="hide()"
            class="fixed inset-0 z-[80] flex items-start justify-center bg-black/40 px-4 pt-[10vh]"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('search.title') }}"
                class="flex max-h-[min(36rem,80vh)] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-secondary-soft bg-white shadow-card-hover dark:border-line dark:bg-surface-1"
                data-test="search-dialog"
            >
                <div class="flex items-center gap-3 border-b border-secondary-soft px-4 dark:border-line">
                    <flux:icon.magnifying-glass variant="mini" class="size-5 shrink-0 text-secondary-medium dark:text-ink-muted" />
                    <input
                        id="{{ $uid }}-input"
                        type="search"
                        wire:model.live.debounce.200ms="term"
                        x-on:input="active = 0"
                        x-on:keydown="key($event)"
                        placeholder="{{ __('search.placeholder') }}"
                        autocomplete="off"
                        spellcheck="false"
                        class="h-14 min-w-0 flex-1 bg-transparent text-base text-secondary-dark outline-none placeholder:text-secondary-light dark:text-ink dark:placeholder:text-ink-faint"
                        aria-label="{{ __('search.title') }}"
                        data-test="search-input"
                    />
                    <x-ui.loader wire:loading.grid wire:target="term" class="text-lg" />
                </div>

                <div id="{{ $uid }}-results" class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2" data-test="search-results">
                    @if ($tooShort)
                        <p class="px-3 py-6 text-center text-sm text-secondary-medium dark:text-ink-muted">{{ __('search.hint') }}</p>
                    @elseif ($groups === [])
                        <p class="px-3 py-6 text-center text-sm text-secondary-medium dark:text-ink-muted" data-test="search-empty">{{ __('search.none', ['term' => trim($term)]) }}</p>
                    @else
                        @foreach ($groups as $group => $results)
                            <section wire:key="search-{{ $group }}" class="pb-1">
                                <h2 class="px-3 pb-1 pt-2 text-xs font-semibold text-secondary-medium dark:text-ink-muted">{{ __('search.groups.'.$group) }}</h2>
                                <ul role="list">
                                    @foreach ($results as $result)
                                        <li wire:key="search-{{ $group }}-{{ $loop->index }}">
                                            <a
                                                href="{{ $result['url'] }}"
                                                wire:navigate
                                                x-on:click="hide()"
                                                x-on:mouseenter="active = results().indexOf($el)"
                                                x-bind:data-active="results().indexOf($el) === active"
                                                class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-start data-active:bg-surface-2 dark:data-active:bg-white/[0.06]"
                                                data-result
                                                data-test="search-result"
                                            >
                                                <flux:icon :name="$icons[$group]" variant="mini" class="size-4 shrink-0 text-secondary-medium dark:text-ink-muted" />
                                                <span class="flex min-w-0 flex-1 flex-col">
                                                    <span class="truncate text-sm font-semibold text-secondary-dark dark:text-ink"><bdi>{{ $result['label'] }}</bdi></span>
                                                    @if (filled($result['detail']))
                                                        <span class="truncate text-xs text-secondary-medium dark:text-ink-muted"><bdi>{{ $result['detail'] }}</bdi></span>
                                                    @endif
                                                </span>
                                                <flux:icon.arrow-turn-down-left variant="micro" class="size-3.5 shrink-0 text-secondary-light opacity-0 rtl:-scale-x-100 [[data-active]_&]:opacity-100 dark:text-ink-faint" />
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-secondary-soft bg-surface-2 px-4 py-2 text-xs text-secondary-medium dark:border-line dark:bg-surface-2 dark:text-ink-muted" aria-hidden="true">
                    <span class="flex items-center gap-1"><x-ui.kbd>↑</x-ui.kbd><x-ui.kbd>↓</x-ui.kbd> {{ __('search.keys.move') }}</span>
                    <span class="flex items-center gap-1"><x-ui.kbd>Enter</x-ui.kbd> {{ __('search.keys.open') }}</span>
                    <span class="flex items-center gap-1"><x-ui.kbd>Esc</x-ui.kbd> {{ __('search.keys.close') }}</span>
                </div>
            </div>
        </div>
    @endteleport
</div>
