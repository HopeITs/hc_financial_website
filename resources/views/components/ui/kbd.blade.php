{{-- One key of a keyboard shortcut. Keys stay left to right inside Arabic text. --}}
<kbd dir="ltr" {{ $attributes->class('inline-flex h-6 min-w-6 items-center justify-center rounded-md border border-secondary-soft border-b-2 bg-surface-2 px-1.5 font-sans text-xs font-semibold text-secondary-dark dark:border-line dark:bg-surface-3 dark:text-ink') }}>{{ $slot }}</kbd>
