<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('assets.count.title', ['number' => $count->id]) }}</title>
        @vite(['resources/css/app.css'])
        <link rel="stylesheet" href="{{ asset('css/pos-print.css') }}">
    </head>
    {{-- A count sheet of the fixed assets (finance request of 2026-11-28), to fill by hand or keep once closed. --}}
    <body class="pos-paper" data-test="asset-count-print">
        <div class="pos-bar">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ __('assets.print') }}</x-ui.button>
        </div>

        <main class="pos-sheet">
            <img class="pos-logo" src="{{ asset('logo.png') }}" alt="">
            <p class="pos-title">{{ __('assets.count.title', ['number' => $count->id]) }}</p>
            <dl class="pos-meta">
                <dt>{{ __('assets.fields.warehouse') }}</dt><dd>{{ $count->warehouse?->name ?? __('assets.all_warehouses') }}</dd>
                <dt>{{ __('assets.fields.date') }}</dt><dd dir="ltr">{{ $count->counted_on->format('d/m/Y') }}</dd>
                <dt>{{ __('assets.count.by') }}</dt><dd>{{ $count->counted_by_name ?? $counter }}</dd>
                <dt>{{ __('assets.fields.status') }}</dt><dd>{{ __('assets.count.states.'.$count->status) }}</dd>
            </dl>

            <table class="pos-table">
                <thead><tr><th>#</th><th>{{ __('assets.fields.tag') }}</th><th>{{ __('assets.fields.name') }}</th><th>{{ __('assets.fields.serial') }}</th><th>{{ __('assets.fields.custodian') }}</th><th>{{ __('assets.count.found_q') }}</th><th>{{ __('assets.fields.condition') }}</th><th>{{ __('assets.fields.notes') }}</th></tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td dir="ltr">{{ $line->asset->tag }}</td>
                            <td>{{ $line->asset->name }}</td>
                            <td dir="ltr">{{ $line->asset->serial }}</td>
                            <td>{{ $line->asset->custodian }}</td>
                            <td>{{ $line->found === null ? '☐' : ($line->found ? __('assets.count.found') : __('assets.count.missing')) }}</td>
                            <td>{{ $line->condition ? __('assets.conditions.'.$line->condition) : '' }}</td>
                            <td>{{ $line->note }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="pos-table">
                <thead><tr><th>{{ __('assets.count.by') }}</th><th>{{ __('assets.paper.approved_by') }}</th></tr></thead>
                <tbody><tr><td><br><br>{{ __('assets.paper.signature') }}</td><td><br><br>{{ __('assets.paper.signature') }}</td></tr></tbody>
            </table>
        </main>
    </body>
</html>
