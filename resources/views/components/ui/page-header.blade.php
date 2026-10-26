@props(['title', 'count' => null, 'subtitle' => null])

@php
    // Where the page sits in the menu: its group, as in the mockup's small line above the title.
    $screen = request()->route('screen');
    $crumb = request()->routeIs('chart') ? 'chart' : collect((array) config('navigation.groups'))->filter(fn (array $screens): bool => $screen !== null && in_array($screen, $screens, true))->keys()->first();
@endphp

{{-- Page title with an optional count badge, a one-line subtitle, and actions on the other side. --}}
<header data-page-header {{ $attributes->class('flex flex-wrap items-end justify-between gap-4 pb-1') }}>
    <div class="min-w-0">
        @if ($crumb !== null)
            <p class="page-crumb" data-test="crumb">{{ __('navigation.groups.'.$crumb) }}</p>
        @endif
        <div class="flex items-center gap-2.5">
            <flux:heading size="xl" level="1" class="text-2xl! font-semibold! leading-tight!">{{ $title }}</flux:heading>
            @if ($count !== null)
                <span data-count-badge class="inline-flex min-w-7 items-center justify-center rounded-full bg-secondary-soft px-2.5 py-0.5 text-xs font-semibold tabular-nums text-secondary-dark dark:bg-surface-2 dark:text-ink">{{ $count }}</span>
            @endif
        </div>
        @if ($subtitle !== null)
            <p class="mt-1.5 text-sm text-secondary-medium dark:text-ink-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div data-page-header-actions class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
