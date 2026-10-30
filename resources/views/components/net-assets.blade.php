@props(['net', 'note' => null])

{{-- Net assets as computed (F-05, request 03): assets minus liabilities, split by donor restriction. Always in USD. --}}
<x-ui.panel class="flex flex-col gap-3 p-4" data-test="net-assets">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-sm font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.net_assets_panel.title') }}</h2>
        <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ $note ?? __('ledger.net_assets_panel.note') }}</p>
    </div>
    <dl class="grid gap-3 sm:grid-cols-3">
        @foreach (['total', 'unrestricted', 'restricted'] as $part)
            <div class="flex flex-col gap-0.5">
                <dt class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.net_assets_panel.'.$part) }}</dt>
                <dd @class(['tabular-nums', 'text-lg font-semibold text-secondary-dark dark:text-ink' => $part === 'total', 'font-medium' => $part !== 'total']) dir="ltr" data-test="net-assets-{{ $part }}">
                    <x-money :amount="$net[$part]" /> <span class="text-xs text-ink-muted">USD</span>
                </dd>
            </div>
        @endforeach
    </dl>
</x-ui.panel>
