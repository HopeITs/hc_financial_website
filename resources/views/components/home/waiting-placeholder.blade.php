{{-- While the "Waiting for you" panel loads: one quiet line, so the page below does not jump much when the panel arrives. --}}
<div class="flex min-h-12 items-center gap-2.5 text-sm text-secondary-medium dark:text-ink-muted" role="status" data-test="home-waiting-loading">
    <x-ui.loader class="text-lg" />
    {{ __('home.waiting.loading') }}
</div>
