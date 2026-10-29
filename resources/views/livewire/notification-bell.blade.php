<div wire:poll.60s data-test="notification-bell">
    <flux:dropdown position="bottom" align="end">
        <flux:button
            variant="ghost"
            size="sm"
            square
            class="relative size-8! touch-manipulation text-secondary-medium! hover:text-secondary-dark! dark:text-ink-muted! dark:hover:text-ink!"
            :aria-label="__('notifications.bell_label', ['count' => $unread])"
            data-test="bell-button"
        >
            <flux:icon.bell variant="outline" class="size-4.5!" />
            @if ($unread > 0)
                <span class="nav-badge absolute -end-1 -top-1" data-test="bell-count">{{ $unread > 99 ? '99+' : $unread }}</span>
            @endif
        </flux:button>

        <flux:menu class="w-[23rem] max-w-[calc(100vw-1.5rem)] overflow-hidden p-0! dark:border-line! dark:bg-surface-1!" data-test="bell-panel">
            <div class="flex items-center justify-between gap-3 border-b border-secondary-soft px-4 py-3 dark:border-line">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-secondary-dark dark:text-ink">{{ __('notifications.title') }}</h2>
                    @if ($unread > 0)
                        <span class="inline-flex items-center rounded-full bg-status-active-soft px-2 py-0.5 text-[11px] font-semibold tabular-nums text-status-active">{{ __('notifications.unread_count', ['count' => $unread]) }}</span>
                    @endif
                </div>
                @if ($unread > 0)
                    <button type="button" wire:click="markAllRead" class="rounded text-xs font-semibold text-secondary-medium hover:text-secondary-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:text-ink-muted dark:hover:text-ink" data-test="bell-mark-all">{{ __('notifications.mark_all') }}</button>
                @endif
            </div>

            @if ($latest->isEmpty())
                <x-ui.empty-state icon="bell" :heading="__('notifications.empty_heading')" :body="__('notifications.empty')" class="py-8!" data-test="bell-empty" />
            @else
                <div class="max-h-[min(28rem,65vh)] overflow-y-auto overscroll-contain p-1.5">
                    @foreach ($latest as $notification)
                        <flux:menu.item
                            wire:click="open({{ $notification->id }})"
                            wire:key="notification-{{ $notification->id }}"
                            @class([
                                'h-auto! items-start! rounded-lg! px-2.5! py-2.5! whitespace-normal!',
                                'bg-surface-2 dark:bg-white/[0.03]' => $notification->isUnread(),
                            ])
                            data-test="bell-item"
                        >
                            <x-notification-item :notification="$notification" compact />
                        </flux:menu.item>
                    @endforeach
                </div>
            @endif

            <div class="border-t border-secondary-soft p-1.5 dark:border-line">
                <flux:menu.item :href="route('notifications')" wire:navigate class="justify-center! text-secondary-dark! dark:text-ink!" data-test="bell-view-all">
                    {{ __('notifications.view_all') }}
                </flux:menu.item>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
