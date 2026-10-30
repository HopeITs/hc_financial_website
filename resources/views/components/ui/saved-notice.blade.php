{{-- The "voucher saved" line an entry screen shows after Ctrl+Enter saved the last entry and opened a fresh form (U-07). --}}
@if (session('notice'))
    <div class="flex items-center gap-2 rounded-lg border border-status-good/30 bg-status-good-soft px-4 py-2 text-sm text-secondary-dark dark:text-ink" role="status" data-test="saved-notice">
        <flux:icon.check-circle variant="mini" class="size-4 shrink-0 text-status-good" />
        {{ session('notice') }}
    </div>
@endif
