@props(['title'])

@php
    // Bar widths per row, so the skeleton reads as a table rather than a stack of identical stripes.
    $rows = [['w-16', 'w-48', 'w-24'], ['w-16', 'w-36', 'w-20'], ['w-16', 'w-56', 'w-24'], ['w-16', 'w-40', 'w-16'], ['w-16', 'w-44', 'w-24'], ['w-16', 'w-32', 'w-20']];
@endphp

{{-- A screen's placeholder while its figures load: the real title, the loading mark with a status line, the rail under the top bar, and static grey shapes for the filters and the table (no shimmer). --}}
<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" aria-busy="true" data-test="report-skeleton">
    <x-ui.page-header :title="$title" />

    <p class="flex items-center gap-3 text-sm font-medium text-secondary-dark dark:text-ink" role="status">
        <x-ui.loader class="text-[1.75rem]" />
        {{ __('common.loading_report') }}
    </p>

    <div class="hc-rail" aria-hidden="true"></div>

    <div class="grid gap-4 sm:grid-cols-3" aria-hidden="true">
        @foreach (range(1, 3) as $field)
            <div class="flex flex-col gap-2">
                <span class="h-3 w-20 rounded bg-secondary-soft dark:bg-surface-3"></span>
                <span class="h-11 rounded-lg border border-secondary-soft bg-surface-2 dark:border-line dark:bg-surface-2"></span>
            </div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-secondary-soft bg-white shadow-card dark:border-transparent dark:bg-surface-1 dark:shadow-raised" aria-hidden="true">
        <div class="flex items-center gap-6 border-b border-secondary-soft bg-surface-2 px-4 py-3 dark:border-line">
            <span class="h-3 w-16 rounded bg-secondary-soft dark:bg-surface-3"></span>
            <span class="h-3 w-28 rounded bg-secondary-soft dark:bg-surface-3"></span>
            <span class="ms-auto h-3 w-20 rounded bg-secondary-soft dark:bg-surface-3"></span>
        </div>
        @foreach ($rows as [$code, $name, $amount])
            <div class="flex min-h-11 items-center gap-6 border-b border-secondary-soft px-4 last:border-b-0 dark:border-line">
                <span class="h-3 {{ $code }} rounded bg-surface-2 dark:bg-surface-3"></span>
                <span class="h-3 {{ $name }} rounded bg-surface-2 dark:bg-surface-3"></span>
                <span class="ms-auto h-3 {{ $amount }} rounded bg-surface-2 dark:bg-surface-3"></span>
            </div>
        @endforeach
    </div>
</div>
