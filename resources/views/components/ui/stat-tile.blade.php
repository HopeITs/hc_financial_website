@props(['label', 'value', 'unit' => null, 'tone' => null, 'icon' => null])

@php
    $valueTone = match ($tone) {
        'good' => 'text-status-good',
        'warn' => 'text-status-warn',
        'critical' => 'text-status-critical',
        default => 'text-secondary-dark dark:text-ink',
    };
    $iconTone = match ($tone) {
        'good' => 'bg-status-good-soft text-status-good',
        'warn' => 'bg-status-warn-soft text-status-warn',
        'critical' => 'bg-status-critical-soft text-status-critical',
        default => 'bg-secondary-soft text-secondary-dark dark:bg-surface-2 dark:text-ink',
    };
@endphp

{{-- A key figure: label, number, unit (the currency). Static: no hover lift. --}}
<div data-stat-tile {{ $attributes->class('rounded-xl border border-secondary-soft bg-white p-4 shadow-card dark:border-transparent dark:bg-surface-1 dark:shadow-raised') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="type-label text-secondary-medium dark:text-ink-muted">{{ $label }}</p>
            <p class="type-figure mt-2 flex items-baseline gap-1.5 {{ $valueTone }}" dir="ltr">
                <span>{{ $value }}</span>
                @if ($unit !== null)
                    <span class="type-meta font-normal text-secondary-medium dark:text-ink-muted">{{ $unit }}</span>
                @endif
            </p>
        </div>
        @if ($icon !== null)
            <div class="{{ $iconTone }} flex size-10 shrink-0 items-center justify-center rounded-xl">
                <flux:icon :name="$icon" class="size-5" />
            </div>
        @endif
    </div>
</div>
