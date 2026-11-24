<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="operations-home">
    <x-ui.page-header :title="__('navigation.screens.operations')" :subtitle="__('operations_home.intro')" />

    @if ($pendingVouchers > 0)
        <a href="{{ $approvalUrl }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-status-warn bg-status-warn-soft px-4 py-3 text-sm text-status-warn" data-test="pending-vouchers">
            <flux:icon.clock variant="mini" class="size-5" />
            <span>{{ trans_choice('operations_home.pending_vouchers', $pendingVouchers, ['count' => $pendingVouchers]) }}</span>
        </a>
    @endif

    @forelse ($sections as $section => $cards)
        <section class="flex flex-col gap-3" data-test="section-{{ $section }}">
            <div class="flex items-baseline gap-3">
                <span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">{{ $loop->iteration }}</span>
                <div>
                    <h2 class="font-semibold text-secondary-dark dark:text-ink">{{ __('operations_home.sections.'.$section.'.title') }}</h2>
                    <p class="text-sm text-secondary-medium dark:text-ink-muted">{{ __('operations_home.sections.'.$section.'.hint') }}</p>
                </div>
            </div>
            <ul role="list" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($cards as $key => $card)
                    <li wire:key="card-{{ $key }}">
                        <a href="{{ $card['url'] }}" wire:navigate class="group flex h-full items-start gap-3 rounded-xl border border-secondary-soft bg-white px-4 py-3 shadow-card hover:border-primary/40 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-primary dark:border-transparent dark:bg-surface-1 dark:shadow-raised dark:hover:bg-white/[0.04]" data-test="card-{{ $key }}">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary-soft text-primary dark:bg-surface-2 dark:text-brand-text" aria-hidden="true">
                                <flux:icon :name="$card['icon']" variant="mini" class="size-5" />
                            </span>
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('operations_home.cards.'.$key.'.title') }}</span>
                                    @if (($card['count'] ?? 0) > 0)
                                        <span class="nav-badge" data-test="count-{{ $key }}">{{ $card['count'] }}</span>
                                    @endif
                                </span>
                                <span class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('operations_home.cards.'.$key.'.hint') }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <x-ui.empty-state icon="briefcase" :heading="__('operations_home.none')" />
    @endforelse
</div>
