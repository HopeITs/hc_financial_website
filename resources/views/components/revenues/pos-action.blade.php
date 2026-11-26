@props(['icon', 'label', 'primary' => false])

{{-- A big action button of the point of sale's order screen (finance request of 2026-11-27): an icon above its label. --}}
<button type="button" {{ $attributes->class([
    'flex min-h-20 w-full touch-manipulation flex-col items-center justify-center gap-2 rounded-xl border px-3 py-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50',
    'border-status-good bg-status-good text-white hover:opacity-90' => $primary,
    'border-secondary-soft bg-board text-secondary-dark hover:bg-secondary-soft dark:border-line dark:bg-surface-2 dark:text-ink dark:hover:bg-surface-3' => ! $primary,
]) }}>
    <flux:icon :name="$icon" variant="outline" class="size-7" />
    <span>{{ $label }}</span>
</button>
