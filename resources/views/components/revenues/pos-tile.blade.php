@props(['name', 'price', 'currency', 'tone' => 1, 'count' => null])

@php
    /*
     * One item on the point-of-sale screen (finance request of 2026-11-26): a coloured tile with its name and price;
     * a tap adds one to the basket. The colour follows the item's category (the chart series colours, 1 to 6).
     */
    $toneClass = match ((int) $tone) {
        2 => 'bg-series-2',
        3 => 'bg-series-3',
        4 => 'bg-series-4',
        5 => 'bg-series-5',
        6 => 'bg-series-6',
        default => 'bg-series-1',
    };
@endphp

<button type="button" {{ $attributes->class(['relative flex min-h-16 touch-manipulation items-center gap-3 rounded-lg px-4 py-3 text-white shadow-sm hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', $toneClass]) }}>
    <flux:icon.calculator variant="solid" class="size-8 shrink-0 opacity-90" />
    <span class="flex min-w-0 flex-1 flex-col items-center text-center">
        <span class="text-sm font-semibold leading-snug">{{ $name }}</span>
        <x-money :amount="$price" class="text-sm text-white! opacity-90" />
    </span>
    @if ($count)
        <span class="absolute -top-2 end-2 inline-flex min-w-6 items-center justify-center rounded-full bg-white px-1.5 text-xs font-bold tabular-nums text-secondary-dark shadow">{{ $count }}</span>
    @endif
</button>
