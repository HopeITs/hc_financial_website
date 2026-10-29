<div class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 pb-10 lg:px-8" wire:poll.60s data-test="notifications-page">
    <x-ui.page-header :title="__('notifications.title')" :count="$unread > 0 ? __('notifications.unread_count', ['count' => $unread]) : null" :subtitle="__('notifications.subtitle')">
        @if ($unread > 0)
            <x-slot:actions>
                <x-ui.button tone="action" icon="check" wire:click="markAllRead" data-test="page-mark-all">{{ __('notifications.mark_all') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($total > 0)
        <div class="flex flex-wrap gap-4" data-test="notification-filters">
            <x-ui.form-segmented
                wire:model.live="show"
                :label="__('notifications.show')"
                :options="[
                    ['value' => 'all', 'label' => __('notifications.show_all'), 'test' => 'show-all'],
                    ['value' => 'unread', 'label' => __('notifications.show_unread'), 'dot' => 'bg-status-active', 'test' => 'show-unread'],
                ]"
                class="w-full sm:w-56"
            />
            <x-ui.form-segmented
                wire:model.live="about"
                :label="__('notifications.about')"
                :options="[
                    ['value' => 'all', 'label' => __('notifications.about_all'), 'test' => 'about-all'],
                    ['value' => 'vouchers', 'label' => __('notifications.about_vouchers'), 'test' => 'about-vouchers'],
                    ['value' => 'requests', 'label' => __('notifications.about_requests'), 'test' => 'about-requests'],
                ]"
                class="w-full sm:w-80"
            />
        </div>
    @endif

    <x-ui.panel>
        @forelse ($days as $day => $items)
            <section wire:key="day-{{ md5($day) }}" data-test="notification-day">
                <h2 class="border-b border-secondary-soft bg-surface-2 px-4 py-2 text-[13px] font-semibold text-secondary-medium dark:border-line dark:text-ink-muted">{{ $day }}</h2>
                <ul role="list">
                    @foreach ($items as $notification)
                        <li
                            wire:key="notification-{{ $notification->id }}"
                            @class([
                                'flex items-stretch border-b border-secondary-soft last:border-b-0 dark:border-line',
                                'bg-status-active-soft/40 dark:bg-white/[0.03]' => $notification->isUnread(),
                            ])
                            data-test="notification-row"
                        >
                            <button
                                type="button"
                                wire:click="open({{ $notification->id }})"
                                class="flex min-h-16 min-w-0 flex-1 px-4 py-3 text-start hover:bg-secondary-soft/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary dark:hover:bg-white/[0.05]"
                                data-test="notification-open"
                            >
                                <x-notification-item :notification="$notification" />
                            </button>
                            <div class="flex w-12 shrink-0 items-center justify-center">
                                @if ($notification->isUnread())
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        square
                                        icon="check"
                                        wire:click="markRead({{ $notification->id }})"
                                        :aria-label="__('notifications.mark_read')"
                                        :tooltip="__('notifications.mark_read')"
                                        class="size-9! text-secondary-medium! hover:text-secondary-dark! dark:text-ink-muted! dark:hover:text-ink!"
                                        data-test="notification-mark-read"
                                    />
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            @if ($total === 0)
                <x-ui.empty-state icon="bell" :heading="__('notifications.empty_heading')" :body="__('notifications.empty')" data-test="notifications-empty" />
            @else
                <x-ui.empty-state icon="funnel" :heading="__('notifications.empty_filtered_heading')" :body="__('notifications.empty_filtered')" data-test="notifications-empty-filtered" />
            @endif
        @endforelse

        @if ($notifications->hasPages())
            <div class="border-t border-secondary-soft px-4 py-2 dark:border-line">{{ $notifications->links() }}</div>
        @endif
    </x-ui.panel>
</div>
