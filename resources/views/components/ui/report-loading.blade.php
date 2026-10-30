{{-- Shown while a report works (a filter changed, an export runs), after a short delay so quick answers do not flash: a rail runs under the top bar and a dark pill at the bottom says so. Nothing on the page moves or is covered. --}}
<div wire:loading.delay class="hidden" data-test="report-loading">
    <div class="hc-rail" aria-hidden="true"></div>

    <div class="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4" role="status">
        <span class="flex items-center gap-3 rounded-full bg-secondary-dark py-2.5 ps-4 pe-5 text-sm font-semibold text-white shadow-card-hover dark:bg-surface-3 dark:text-ink dark:ring-1 dark:ring-line">
            <x-ui.loader class="text-[1.375rem] text-white! dark:text-brand-text!" />
            {{ __('common.loading') }}
        </span>
    </div>
</div>
