@props(['title', 'count' => null, 'subtitle' => null])

{{-- Page title with an optional count badge, a one-line subtitle, and actions on the other side. --}}
<header data-page-header {{ $attributes->class('flex flex-wrap items-end justify-between gap-4 border-b border-secondary-soft pb-6 dark:border-line') }}>
    <div class="min-w-0">
        <div class="flex items-center gap-2.5">
            <flux:heading size="xl" level="1" class="text-3xl! font-bold! leading-none!">{{ $title }}</flux:heading>
            @if ($count !== null)
                <span data-count-badge class="inline-flex min-w-7 items-center justify-center rounded-md bg-primary px-2 py-0.5 text-xs font-bold tabular-nums text-white dark:bg-white/10 dark:text-ink">{{ $count }}</span>
            @endif
        </div>
        @if ($subtitle !== null)
            <p class="mt-2 text-sm text-secondary-medium dark:text-ink-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div data-page-header-actions class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
