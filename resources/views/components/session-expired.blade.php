{{-- U-02: replaces Livewire's English "This page has expired" prompt (resources/js/session-expired.js opens it on a 419). --}}
<div x-data x-on:hc-session-expired.window="$refs.dialog.open || $refs.dialog.showModal()">
    <dialog
        x-ref="dialog"
        class="m-auto w-[min(26rem,calc(100vw-2rem))] rounded-xl border border-secondary-soft bg-white p-0 text-secondary-dark shadow-card-hover backdrop:bg-black/50 dark:border-line dark:bg-surface-1 dark:text-ink"
        aria-labelledby="session-expired-title"
        data-test="session-expired"
    >
        <div class="flex flex-col gap-3 px-6 pb-2 pt-6">
            <span class="flex size-10 items-center justify-center rounded-lg bg-status-warn-soft text-status-warn" aria-hidden="true">
                <flux:icon.clock variant="mini" class="size-5" />
            </span>
            <h2 id="session-expired-title" class="text-lg font-bold">{{ __('errors.expired.title') }}</h2>
            <p class="text-sm leading-relaxed text-secondary-medium dark:text-ink-muted">{{ __('errors.expired.body') }}</p>
        </div>
        <div class="flex justify-end px-6 pb-6 pt-4">
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:bg-brand" data-test="session-expired-sign-in">{{ __('errors.expired.action') }}</a>
        </div>
    </dialog>
</div>
