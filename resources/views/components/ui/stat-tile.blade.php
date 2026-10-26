@props(['label', 'value', 'unit' => null, 'tone' => null, 'icon' => null])

@php
    $valueTone = match ($tone) {
        'good' => 'text-status-good',
        'warn' => 'text-status-warn',
        'critical' => 'text-status-critical',
        default => 'text-secondary-dark dark:text-ink',
    };
    $edge = match ($tone) {
        'good' => 'border-t-status-good',
        'warn' => 'border-t-status-warn',
        'critical' => 'border-t-status-critical',
        default => 'border-t-secondary-light dark:border-t-line-strong',
    };
@endphp

{{-- A key figure, as in the mockup: a thin coloured top edge, the label, then the number and its unit (the currency) on one line. Static: no hover lift. --}}
<div data-stat-tile {{ $attributes->class('min-w-0 rounded-xl border border-t-2 border-secondary-soft bg-white px-4 py-3 shadow-card dark:border-line dark:bg-surface-1 dark:shadow-raised '.$edge) }}>
    <p class="type-label truncate text-secondary-medium dark:text-ink-muted">{{ $label }}</p>
    <p class="type-figure mt-1.5 flex items-baseline gap-1.5 whitespace-nowrap {{ $valueTone }}" dir="ltr">
        <span class="min-w-0 truncate">{{ $value }}</span>
        @if ($unit !== null)
            <span class="type-meta font-normal text-secondary-medium dark:text-ink-muted">{{ $unit }}</span>
        @endif
    </p>
</div>
