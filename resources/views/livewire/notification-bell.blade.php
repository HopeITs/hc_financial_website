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

        <flux:menu class="w-80 max-w-[calc(100vw-2rem)]">
            <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                <flux:heading size="sm">{{ __('notifications.title') }}</flux:heading>
                @if ($unread > 0)
                    <button type="button" wire:click="markAllRead" class="text-xs font-medium text-primary hover:underline dark:text-brand" data-test="bell-mark-all">{{ __('notifications.mark_all') }}</button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($latest as $notification)
                <flux:menu.item wire:click="open({{ $notification->id }})" wire:key="notification-{{ $notification->id }}" class="h-auto! items-start! py-2! whitespace-normal!" data-test="bell-item">
                    <span class="flex w-full items-start gap-2">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-primary dark:bg-brand' => $notification->read_at === null, 'bg-transparent' => $notification->read_at !== null]) aria-hidden="true"></span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span @class(['text-sm leading-snug', 'font-semibold' => $notification->read_at === null])>{{ $notification->message() }}</span>
                            <span class="text-xs text-secondary-medium dark:text-ink-muted">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </span>
                </flux:menu.item>
            @empty
                <p class="px-2 py-4 text-center text-sm text-secondary-medium dark:text-ink-muted" data-test="bell-empty">{{ __('notifications.empty') }}</p>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
