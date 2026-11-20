{{-- While the overview loads: one quiet line, so the page below does not jump much when it arrives. --}}
<div class="flex min-h-12 items-center gap-2.5 text-sm text-secondary-medium dark:text-ink-muted" role="status" data-test="home-overview-loading">
    <x-ui.loader class="text-lg" />
    {{ __('home.overview.loading') }}
</div>
