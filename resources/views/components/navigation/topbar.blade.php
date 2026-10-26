@php
    $groups = \App\Support\Navigation::visibleGroups(auth()->user());
    $currentScreen = request()->route('screen');
    $crumbGroup = collect($groups)->filter(fn (array $screens): bool => in_array($currentScreen, $screens, true))->keys()->first();
    $crumb = match (true) {
        $crumbGroup !== null => __('navigation.groups.'.$crumbGroup),
        request()->routeIs('chart') => __('navigation.groups.chart'),
        default => __('common.app_name'),
    };
@endphp

{{-- The top bar: the menu button on a phone, where the person is, then language, theme and the user. The menu itself is in the sidebar. --}}
<flux:header sticky class="shell-bar z-40 min-h-16 gap-2 border-b border-secondary-soft bg-white px-3 py-2 lg:px-6 dark:border-line dark:bg-surface-1" data-top-bar-actions>
    <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="bars-2" inset="left" :aria-label="__('common.open_menu')" />

    <a href="{{ route('home') }}" wire:navigate class="me-2 shrink-0 touch-manipulation lg:hidden">
        <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-8 w-auto" data-no-dim />
    </a>

    <div class="hidden min-w-0 flex-1 text-sm text-secondary-medium lg:block dark:text-ink-muted" data-test="crumb">
        <span>{{ $crumb }}</span>
        @if ($currentScreen)
            <span class="mx-1.5 text-secondary-light" aria-hidden="true">/</span>
            <span class="font-semibold text-secondary-dark dark:text-ink">{{ __('navigation.screens.'.$currentScreen) }}</span>
        @endif
    </div>

    <div class="ms-auto flex shrink-0 items-center gap-2">
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
