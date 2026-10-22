@php
    $groups = \App\Support\Navigation::visibleGroups(auth()->user());
    $currentScreen = request()->route('screen');
    $selectedCity = request()->routeIs('home') ? (int) request()->query('city') : 0;

    // The tree comes from the legacy database; a page must still open when it is unreachable.
    try {
        $tree = app(\App\Services\Projects\SideTree::class)->cities();
    } catch (\Throwable) {
        $tree = [];
    }

    $itemClasses = 'touch-manipulation rounded-lg! text-secondary-dark! hover:bg-secondary-soft/70! max-lg:h-11! data-current:bg-primary! data-current:font-semibold! data-current:text-white! dark:text-ink-muted! dark:hover:bg-white/[0.06]! dark:hover:text-ink! dark:data-current:bg-surface-2! dark:data-current:text-ink!';
    $rowClasses = 'flex min-h-8 w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-start text-sm text-secondary-dark hover:bg-secondary-soft/70 dark:text-ink-muted dark:hover:bg-white/[0.06] dark:hover:text-ink';
@endphp

{{-- The main sidebar: the screens by group, then the projects tree (governorate, program, sub-project, account). --}}
<flux:sidebar
    sticky
    stashable
    aria-label="{{ __('common.app_name') }}"
    data-navigation-sidebar
    class="z-50! w-72! gap-3! border-e border-secondary-soft bg-white p-3! text-secondary-dark dark:border-line dark:bg-surface-1 dark:text-ink"
>
    <flux:sidebar.header class="-mx-3 -mt-3 h-16 shrink-0 border-b border-secondary-soft px-3 dark:border-line">
        <a href="{{ route('home') }}" wire:navigate class="touch-manipulation" data-test="home-link">
            <img src="{{ asset('logo.png') }}" alt="{{ __('common.app_name') }}" class="h-9 w-auto" data-no-dim />
        </a>

        <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="x-mark" />
    </flux:sidebar.header>

    <flux:sidebar.nav class="min-h-0 flex-1 gap-1 overflow-y-auto">
        <flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home') && $selectedCity === 0" :class="$itemClasses" wire:navigate>
            {{ __('home.title') }}
        </flux:sidebar.item>

        @foreach ($groups as $group => $screens)
            @if (count($screens) === 1)
                <flux:sidebar.item :href="route('screens.show', $screens[0])" :current="$currentScreen === $screens[0]" :class="$itemClasses">
                    {{ __('navigation.groups.'.$group) }}
                </flux:sidebar.item>
            @else
                <flux:sidebar.group expandable :expanded="in_array($currentScreen, $screens, true)" :heading="__('navigation.groups.'.$group)">
                    @foreach ($screens as $screen)
                        <flux:sidebar.item :href="route('screens.show', $screen)" :current="$currentScreen === $screen" :class="$itemClasses">
                            {{ __('navigation.screens.'.$screen) }}
                        </flux:sidebar.item>
                    @endforeach
                </flux:sidebar.group>
            @endif
        @endforeach

        <div class="mt-3 border-t border-secondary-soft pt-3 dark:border-line" data-test="side-tree">
            <p class="type-label px-2 pb-2 text-secondary-light dark:text-ink-faint">{{ __('home.side_tree') }}</p>

            @forelse ($tree as $city)
                <details class="group" @if ($selectedCity === $city['id']) open @endif>
                    <summary class="{{ $rowClasses }} list-none font-medium {{ $selectedCity === $city['id'] ? 'bg-secondary-soft/60 dark:bg-white/[0.06]' : '' }}">
                        <flux:icon.chevron-right variant="micro" class="size-3.5 shrink-0 text-secondary-light group-open:rotate-90 rtl:-scale-x-100" />
                        <span class="min-w-0 flex-1 truncate">{{ $city['name'] }}</span>
                        <a
                            href="{{ $selectedCity === $city['id'] ? route('home') : route('home', ['city' => $city['id']]) }}"
                            wire:navigate
                            class="shrink-0 text-xs text-primary hover:underline dark:text-brand-text"
                        >{{ $selectedCity === $city['id'] ? __('home.clear_filter') : __('home.filter') }}</a>
                    </summary>

                    <ul class="ms-3 border-s border-secondary-soft ps-1 dark:border-line">
                        @foreach ($city['programs'] as $program)
                            <li>
                                <details class="group">
                                    <summary class="{{ $rowClasses }} list-none">
                                        <flux:icon.chevron-right variant="micro" class="size-3.5 shrink-0 text-secondary-light group-open:rotate-90 rtl:-scale-x-100" />
                                        <span class="truncate">{{ $program['name'] }}</span>
                                    </summary>

                                    <ul class="ms-3 border-s border-secondary-soft ps-1 dark:border-line">
                                        @foreach ($program['subProjects'] as $subProject)
                                            <li>
                                                <details class="group">
                                                    <summary class="{{ $rowClasses }} list-none text-secondary-medium">
                                                        <flux:icon.chevron-right variant="micro" class="size-3.5 shrink-0 text-secondary-light group-open:rotate-90 rtl:-scale-x-100" />
                                                        <span class="truncate">{{ $subProject['name'] }}</span>
                                                    </summary>

                                                    <ul class="ms-3 border-s border-secondary-soft ps-2 dark:border-line">
                                                        @foreach ($subProject['accounts'] as $account)
                                                            <li class="truncate px-2 py-1 text-xs text-secondary-medium dark:text-ink-muted">{{ $account }}</li>
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
                <p class="px-2 py-1.5 text-sm text-secondary-medium dark:text-ink-muted">{{ __('home.no_tree') }}</p>
            @endforelse
        </div>
    </flux:sidebar.nav>
</flux:sidebar>

<flux:header sticky class="z-40 min-h-16 gap-2 border-b border-secondary-soft bg-white px-3 py-2 lg:px-4 dark:border-line dark:bg-surface-1" data-top-bar-actions>
    <flux:sidebar.toggle class="size-11! touch-manipulation lg:hidden" icon="bars-2" inset="left" :aria-label="__('common.open_menu')" />

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
