@php
    $groups = config('navigation.groups');
    $currentScreen = request()->route('screen');
    $triggerClasses = 'touch-manipulation text-secondary-dark! hover:bg-secondary-soft/70! dark:text-ink-muted! dark:hover:bg-white/[0.06]! dark:hover:text-ink!';
    $activeClasses = 'bg-primary! text-white! hover:bg-primary! dark:bg-surface-2! dark:text-ink!';
    $user = auth()->user();
@endphp

{{-- Mobile drawer: every group, expandable. --}}
<flux:sidebar stashable sticky class="border-e border-secondary-soft bg-white lg:hidden dark:border-line dark:bg-surface-1">
    <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

    <a href="{{ route('home') }}" class="px-2">
        <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-10 w-auto" data-no-dim />
    </a>

    <flux:sidebar.nav>
        @foreach ($groups as $group => $screens)
            @if (count($screens) === 1)
                <flux:sidebar.item :href="route('screens.show', $screens[0])" :current="$currentScreen === $screens[0]">
                    {{ __('navigation.groups.'.$group) }}
                </flux:sidebar.item>
            @else
                <flux:sidebar.group expandable :expanded="in_array($currentScreen, $screens, true)" :heading="__('navigation.groups.'.$group)">
                    @foreach ($screens as $screen)
                        <flux:sidebar.item :href="route('screens.show', $screen)" :current="$currentScreen === $screen">
                            {{ __('navigation.screens.'.$screen) }}
                        </flux:sidebar.item>
                    @endforeach
                </flux:sidebar.group>
            @endif
        @endforeach
    </flux:sidebar.nav>
</flux:sidebar>

<flux:header sticky class="z-40 min-h-16 gap-1 border-b border-secondary-soft bg-white px-3 py-2 lg:px-4 dark:border-line dark:bg-surface-1" data-top-navigation>
    <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="bars-2" inset="left" :aria-label="__('common.open_menu')" />

    <a href="{{ route('home') }}" class="me-4 hidden shrink-0 lg:block" data-test="home-link">
        <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-9 w-auto" data-no-dim />
    </a>

    {{-- Desktop: one menu per group; a group of one screen is a plain link. --}}
    <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('common.app_name') }}">
        @foreach ($groups as $group => $screens)
            @php($isActive = in_array($currentScreen, $screens, true))

            @if (count($screens) === 1)
                <flux:button
                    :href="route('screens.show', $screens[0])"
                    variant="ghost"
                    size="sm"
                    class="{{ $triggerClasses }} {{ $isActive ? $activeClasses : '' }}"
                >
                    {{ __('navigation.groups.'.$group) }}
                </flux:button>
            @else
                <flux:dropdown position="bottom" align="start">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon-trailing="chevron-down"
                        class="{{ $triggerClasses }} {{ $isActive ? $activeClasses : '' }}"
                    >
                        {{ __('navigation.groups.'.$group) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($screens as $screen)
                            <flux:menu.item :href="route('screens.show', $screen)" class="touch-manipulation">
                                {{ __('navigation.screens.'.$screen) }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif
        @endforeach
    </nav>

    <flux:spacer />

    <div class="flex items-center gap-2">
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
                    :name="$user->name"
                    :initials="$user->initials()"
                    circle
                    class="touch-manipulation text-secondary-dark! hover:bg-secondary-soft/70! dark:text-ink! dark:hover:bg-white/10!"
                    data-test="user-menu-button"
                />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="$user->name" :initials="$user->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                            <flux:text class="truncate">{{ $user->roleRecord?->name }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer rtl:[&_svg]:-scale-x-100"
                            data-test="logout-button"
                        >
                            {{ __('common.log_out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>
</flux:header>
