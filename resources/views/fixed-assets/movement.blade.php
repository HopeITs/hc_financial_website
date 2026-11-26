@php
    // A fixed asset's movement note to sign: receiving, handover, return, transfer, repair, write-off or sale (finance
    // request of 2026-11-28). The styles are public/css/pos-print.css, which needs no build.
    $amount = fn ($value): string => number_format((float) $value, 2);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('assets.kinds.'.$movement->kind) }} · {{ $asset->tag }}</title>
        @vite(['resources/css/app.css'])
        <link rel="stylesheet" href="{{ asset('css/pos-print.css') }}">
    </head>
    <body class="pos-paper" data-test="asset-movement-print">
        <div class="pos-bar">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ __('assets.print') }}</x-ui.button>
        </div>

        <main class="pos-sheet">
            <img class="pos-logo" src="{{ asset('logo.png') }}" alt="">
            <p class="pos-title">{{ __('assets.paper.'.$movement->kind) }}</p>
            <p class="pos-center">{{ __('assets.paper.number', ['number' => $movement->id]) }} · <span dir="ltr">{{ $movement->happened_on->format('d/m/Y') }}</span></p>

            <table class="pos-table">
                <tbody>
                    <tr><th>{{ __('assets.fields.tag') }}</th><td dir="ltr">{{ $asset->tag }}</td><th>{{ __('assets.fields.name') }}</th><td>{{ $asset->name }}</td></tr>
                    <tr><th>{{ __('assets.fields.category') }}</th><td>{{ $asset->category?->name() }}</td><th>{{ __('assets.fields.serial') }}</th><td dir="ltr">{{ $asset->serial }}</td></tr>
                    <tr><th>{{ __('assets.fields.project') }}</th><td>{{ $project?->name ?? $asset->project_code }}</td><th>{{ __('assets.fields.center') }}</th><td>{{ $asset->center }}</td></tr>
                    <tr><th>{{ __('assets.paper.from') }}</th><td>{{ collect([$from?->name, $movement->from_custodian])->filter()->implode(' · ') }}</td><th>{{ __('assets.paper.to') }}</th><td>{{ collect([$to?->name, $movement->to_custodian])->filter()->implode(' · ') }}</td></tr>
                    <tr><th>{{ __('assets.fields.condition') }}</th><td>{{ $movement->condition ? __('assets.conditions.'.$movement->condition) : '' }}</td><th>{{ __('assets.fields.reference') }}</th><td>{{ $movement->reference }}</td></tr>
                    @if ($movement->amount_usd !== null)
                        <tr><th>{{ __('assets.paper.amount') }}</th><td dir="ltr">{{ $amount($movement->amount_usd) }} USD</td><th>{{ __('assets.fields.voucher') }}</th><td dir="ltr">{{ $movement->voucher_id }}</td></tr>
                    @endif
                    <tr><th>{{ __('assets.fields.book') }}</th><td dir="ltr">{{ $amount($bookValue) }} USD</td><th>{{ __('assets.fields.notes') }}</th><td>{{ $movement->notes }}</td></tr>
                </tbody>
            </table>

            <table class="pos-table">
                <thead><tr><th>{{ __('assets.paper.handed_by') }}</th><th>{{ __('assets.paper.received_by') }}</th><th>{{ __('assets.paper.approved_by') }}</th></tr></thead>
                <tbody><tr><td>{{ $preparer }}<br><br>{{ __('assets.paper.signature') }}</td><td>{{ $movement->to_custodian }}<br><br>{{ __('assets.paper.signature') }}</td><td><br><br>{{ __('assets.paper.signature') }}</td></tr></tbody>
            </table>
        </main>
    </body>
</html>
