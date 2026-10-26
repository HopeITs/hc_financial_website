@php
    $groups = \App\Support\Navigation::visibleGroups(auth()->user());
    $chartVisible = isset($groups['chart']);
    unset($groups['chart']);
    $currentScreen = request()->route('screen');
    $badges = \App\Support\Navigation::badges(auth()->user());
@endphp

{{-- The top bar: logo, one pill per menu group (each opens a panel of screen tiles; the chart of accounts has the sidebar), language, theme and the user. --}}
<flux:header
    sticky
    class="shell-bar z-40 min-h-16 gap-2 border-b border-secondary-soft bg-white px-3 py-2 lg:px-4 dark:border-line dark:bg-surface-1"
    data-top-bar-actions
    x-data="{ open: null }"
    x-bind:class="open && 'z-[60]!'"
    x-on:keydown.escape.window="open = null"
    x-on:click.outside="open = null"
>
    <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="bars-2" inset="left" :aria-label="__('common.open_menu')" />

    <a href="{{ route('home') }}" wire:navigate class="me-1 shrink-0 touch-manipulation border-e border-secondary-soft pe-4 dark:border-line" data-test="home-link">
        <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-9 w-auto" data-no-dim />
    </a>

    <nav class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto" aria-label="{{ __('common.app_name') }}" data-test="top-menu">
        @if ($chartVisible)
            <a href="{{ route('chart') }}" wire:navigate class="nav-pill" x-on:click="open = null" @if ($currentScreen === 'chart' || request()->routeIs('chart')) aria-current="page" @endif>{{ __('navigation.groups.chart') }}</a>
        @endif

        @foreach ($groups as $group => $screens)
            @php($groupCount = collect($screens)->sum(fn ($slug) => $badges[$slug] ?? 0))
            <button
                type="button"
                class="nav-pill"
                data-test="menu-{{ $group }}"
                @if (in_array($currentScreen, $screens, true)) aria-current="page" @endif
                x-bind:data-open="open === '{{ $group }}'"
                x-bind:aria-expanded="(open === '{{ $group }}').toString()"
                x-on:click="open = open === '{{ $group }}' ? null : '{{ $group }}'"
            >
                {{ __('navigation.groups.'.$group) }}
                @if ($groupCount > 0)
                    <span class="nav-badge" data-test="badge-{{ $group }}">{{ $groupCount }}</span>
                @endif
                <flux:icon.chevron-down variant="micro" class="size-3 opacity-60" />
            </button>
        @endforeach
    </nav>

    <div class="flex shrink-0 items-center gap-2">
        <x-locale-toggle class="hidden sm:inline-flex" />

        <flux:button
            x-data
            x-on:click="hcToggleAppearance()"
            variant="ghost"
            size="sm"
            square
            class="size-8! touch-manipulation text-secondary-medium! hover:text-secondary-dark! dark:text-ink-muted! dark:hover:text-ink!"
            :aria-label="__('common.appearance')"
            data-theme-toggle
        >
            <flux:icon.sun x-show="! $flux.dark" x-cloak variant="outline" class="size-4.5!" />
            <flux:icon.moon x-show="$flux.dark" x-cloak variant="outline" class="size-4.5!" />
        </flux:button>

        <div class="border-s border-secondary-soft ps-2 dark:border-line">
            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    circle
                    class="touch-manipulation text-secondary-dark! hover:bg-secondary-soft/70! dark:text-ink! dark:hover:bg-white/10!"
                    data-test="user-menu-button"
                />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->roleRecord?->name }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer rtl:[&_svg]:-scale-x-100" data-test="logout-button">
                            {{ __('common.log_out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    {{-- The mega menu: the open group's screens as tiles, each with a one-line description. --}}
    @foreach ($groups as $group => $screens)
        <div class="mega" x-show="open === '{{ $group }}'" x-cloak data-test="mega-{{ $group }}">
            <p class="mega-label">{{ __('navigation.groups.'.$group) }}</p>
            <div class="mega-grid">
                @foreach ($screens as $screen)
                    <a href="{{ route('screens.show', $screen) }}" wire:navigate class="mega-tile" x-on:click="open = null" @if ($currentScreen === $screen) aria-current="page" @endif>
                        <span class="flex items-center justify-between gap-2">
                            <span class="mega-title">{{ __('navigation.screens.'.$screen) }}</span>
                            @if (($badges[$screen] ?? 0) > 0)
                                <span class="nav-badge" data-test="badge-{{ $screen }}">{{ $badges[$screen] }}</span>
                            @endif
                        </span>
                        @if (\Illuminate\Support\Facades\Lang::has('navigation.descriptions.'.$screen))
                            <span class="mega-desc">{{ __('navigation.descriptions.'.$screen) }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</flux:header>
