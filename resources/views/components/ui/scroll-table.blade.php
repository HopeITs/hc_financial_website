@props(['perPage' => 10, 'paginate' => true])

{{-- A scrolling card for a table: the header stays put, rows scroll, and (unless the server paginates) a footer pages through them. --}}
<div
    x-data="pagedTable({ perPage: {{ (int) $perPage }}, paginate: {{ $paginate ? 'true' : 'false' }} })"
    {{ $attributes->class('fin-table-wrap') }}
>
    <div class="fin-table-scroll">
        {{ $slot }}
    </div>

    @if ($paginate)
        <div x-show="pages > 1" x-cloak class="fin-table-pager">
            <span class="tabular-nums" dir="ltr" x-text="`${from}–${to} / ${total}`"></span>
            <div class="flex items-center gap-2">
                <button type="button" class="fin-pager-btn" x-on:click="go(page - 1)" x-bind:disabled="page <= 1" aria-label="{{ __('common.previous') }}">
                    <flux:icon.chevron-left variant="micro" class="size-4 rtl:-scale-x-100" />
                </button>
                <span class="tabular-nums" dir="ltr" x-text="`${page} / ${pages}`"></span>
                <button type="button" class="fin-pager-btn" x-on:click="go(page + 1)" x-bind:disabled="page >= pages" aria-label="{{ __('common.next') }}">
                    <flux:icon.chevron-right variant="micro" class="size-4 rtl:-scale-x-100" />
                </button>
            </div>
        </div>
    @endif
</div>
