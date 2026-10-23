@php
    $groups = \App\Support\Navigation::visibleGroups(auth()->user());
    $chartVisible = isset($groups['chart']);
    unset($groups['chart']);
    $currentScreen = request()->route('screen');
    $badges = \App\Support\Navigation::badges(auth()->user());
@endphp

{{-- The top bar: logo, the menu groups (the chart of accounts has the sidebar), language, theme and the user. --}}
<flux:header sticky class="z-40 min-h-16 gap-2 border-b border-secondary-soft bg-white px-3 py-2 lg:px-4 dark:border-line dark:bg-surface-1" data-top-bar-actions>
    <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="bars-2" inset="left" :aria-label="__('common.open_menu')" />

    <a href="{{ route('home') }}" wire:navigate class="me-2 shrink-0 touch-manipulation" data-test="home-link">
        <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-9 w-auto" data-no-dim />
    </a>

    <nav class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto" aria-label="{{ __('common.app_name') }}" data-test="top-menu">
        @if ($chartVisible)
            <flux:button
                :href="route('chart')"
                wire:navigate
                variant="ghost"
                size="sm"
                class="shrink-0 {{ $currentScreen === 'chart' ? 'bg-primary! text-white!' : '' }}"
            >{{ __('navigation.groups.chart') }}</flux:button>
        @endif

        @foreach ($groups as $group => $screens)
            <flux:dropdown position="bottom" align="start">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon-trailing="chevron-down"
                    class="shrink-0 {{ in_array($currentScreen, $screens, true) ? 'bg-secondary-soft! font-semibold!' : '' }}"
                    data-test="menu-{{ $group }}"
                >
                    {{ __('navigation.groups.'.$group) }}
                    @php($groupCount = collect($screens)->sum(fn ($slug) => $badges[$slug] ?? 0))
                    @if ($groupCount > 0)
                        <span class="ms-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold tabular-nums text-white" data-test="badge-{{ $group }}">{{ $groupCount }}</span>
                    @endif
                </flux:button>

                <flux:menu>
                    @foreach ($screens as $screen)
                        <flux:menu.item :href="route('screens.show', $screen)" wire:navigate :class="$currentScreen === $screen ? 'font-semibold text-primary' : ''">
                            {{ __('navigation.screens.'.$screen) }}
                            @if (($badges[$screen] ?? 0) > 0)
                                <span class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold tabular-nums text-white" data-test="badge-{{ $screen }}">{{ $badges[$screen] }}</span>
                            @endif
                        </flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
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
</flux:header>
