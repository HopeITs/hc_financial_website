@props(['notification', 'compact' => false])

@php
    /** @var \App\Models\WebNotification $notification */
    $kind = $notification->kind;
    $tone = match ($kind->tone()) {
        'warn' => 'bg-status-warn-soft text-status-warn',
        'good' => 'bg-status-good-soft text-status-good',
        default => 'bg-status-critical-soft text-status-critical',
    };
    $unread = $notification->isUnread();
    $created = $notification->created_at;
    // The bell says how long ago; the page groups by day, so a row only needs its time.
    $time = $compact ? ($created->isToday() ? $created->diffForHumans() : $created->translatedFormat('j M')) : $created->format('H:i');
    $project = $notification->data['project'] ?? null;
    $amount = $notification->data['amount'] ?? null;
    $reason = $notification->data['reason'] ?? null;
@endphp

{{-- One notification: a status icon, the short heading, the reference with its project and amount, and a rejection's reason. Shared by the bell and the notifications page. --}}
<span class="flex w-full min-w-0 items-start gap-3" data-test="notification-item">
    <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg', $tone]) aria-hidden="true">
        <flux:icon :name="$kind->icon()" variant="mini" class="size-4" />
    </span>

    <span class="flex min-w-0 flex-1 flex-col gap-1">
        <span class="flex items-baseline gap-2">
            <span @class([
                'min-w-0 flex-1 text-sm leading-snug text-secondary-dark dark:text-ink',
                'font-semibold' => $unread,
                'font-medium' => ! $unread,
            ])>{{ $notification->title() }}</span>
            <time datetime="{{ $created->toIso8601String() }}" title="{{ $created->translatedFormat('j F Y, H:i') }}" class="shrink-0 text-xs tabular-nums text-secondary-medium dark:text-ink-muted">{{ $time }}</time>
            @if ($unread)
                <span class="size-2 shrink-0 self-center rounded-full bg-status-active" data-test="unread-dot"></span>
                <span class="sr-only">{{ __('notifications.unread') }}</span>
            @endif
        </span>

        <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[13px] text-secondary-medium dark:text-ink-muted">
            <span dir="ltr" class="font-semibold tabular-nums text-secondary-dark dark:text-ink">{{ $notification->reference() }}</span>
            @if ($project !== null && $project !== '')
                <span aria-hidden="true">·</span>
                <span dir="ltr">{{ $project }}</span>
            @endif
            @if ($amount !== null && $amount !== '')
                <span aria-hidden="true">·</span>
                <x-money :amount="$amount" currency="USD" class="text-secondary-dark dark:text-ink" />
            @endif
        </span>

        @if ($reason !== null && $reason !== '')
            <span class="mt-0.5 block rounded-md bg-surface-2 px-2.5 py-1.5 text-[13px] leading-snug text-secondary-dark dark:bg-surface-3 dark:text-ink">
                <span @class(['line-clamp-2' => $compact, 'block' => ! $compact])><span class="font-semibold">{{ __('notifications.reason') }}:</span> <bdi>{{ $reason }}</bdi></span>
            </span>
        @endif
    </span>
</span>
