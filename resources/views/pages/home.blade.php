<x-layouts.app>
    <div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
        <div>
            <flux:heading size="xl" level="1">{{ __('common.welcome', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:subheading>{{ __('common.home_intro') }}</flux:subheading>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (\App\Support\Navigation::visibleGroups(auth()->user()) as $group => $screens)
                <section class="rounded-xl border border-secondary-soft bg-white p-5 shadow-card dark:border-line dark:bg-surface-1">
                    <h2 class="type-label mb-3 text-secondary-medium dark:text-ink-muted">{{ __('navigation.groups.'.$group) }}</h2>

                    <ul class="flex flex-col gap-1">
                        @foreach ($screens as $screen)
                            <li>
                                <a
                                    href="{{ route('screens.show', $screen) }}"
                                    class="block rounded-lg px-2 py-1.5 text-sm text-secondary-dark hover:bg-secondary-soft/60 dark:text-ink dark:hover:bg-white/[0.06]"
                                >
                                    {{ __('navigation.screens.'.$screen) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
