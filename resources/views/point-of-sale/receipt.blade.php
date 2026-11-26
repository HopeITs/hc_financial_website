@php
    // An order's receipt on an 80 mm till roll (finance request of 2026-11-27); the styles are public/css/pos-print.css.
    $money = fn ($value): string => number_format((float) $value, 2);
    $quantity = fn ($value): string => rtrim(rtrim((string) $value, '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('revenues.pos.receipt') }} {{ $order->number }}</title>
        @vite(['resources/css/app.css'])
        <link rel="stylesheet" href="{{ asset('css/pos-print.css') }}">
    </head>
    <body class="pos-paper" data-test="pos-receipt">
        <div class="pos-bar">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ __('revenues.pos.print') }}</x-ui.button>
        </div>

        <main class="pos-roll">
            <img class="pos-logo" src="{{ asset('logo.png') }}" alt="">
            <p class="pos-title">{{ $project?->name ?? $shift->project_code }}</p>
            <p class="pos-center">{{ $order->isOpen() ? __('revenues.pos.bill') : __('revenues.pos.receipt') }}</p>

            <dl class="pos-meta">
                <dt>{{ __('revenues.pos.invoice') }}</dt><dd>{{ $order->number }}</dd>
                <dt>{{ __('revenues.day') }}</dt><dd dir="ltr">{{ ($order->closed_at ?? $order->created_at)?->format('Y-m-d H:i') }}</dd>
                <dt>{{ __('revenues.pos.service') }}</dt><dd>{{ __('revenues.pos.services.'.$order->service) }}@if ($order->table_label) · {{ $order->table_label }}@endif</dd>
                @if ($order->customer)
                    <dt>{{ __('revenues.pos.customer') }}</dt><dd>{{ $order->customer }}</dd>
                @endif
                @if ($order->waiter)
                    <dt>{{ __('revenues.pos.waiter') }}</dt><dd>{{ $order->waiter }}</dd>
                @endif
            </dl>

            <table class="pos-table">
                <thead><tr><th>{{ __('revenues.item_name') }}</th><th class="num">{{ __('revenues.quantity') }}</th><th class="num">{{ __('revenues.pos.line_total') }}</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>{{ $line->description }}@if ($line->note)<div class="pos-note">{{ $line->note }}</div>@endif</td>
                            <td class="num">{{ $quantity($line->quantity) }} × {{ $money($line->unit_price) }}</td>
                            <td class="num">{{ $money(bcmul((string) $line->quantity, (string) $line->unit_price, 2)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pos-total"><span>{{ __('revenues.pos.gross') }}</span><strong>{{ $money($order->gross) }}</strong></div>
            @if ((float) $order->discount > 0)
                <div class="pos-total"><span>{{ __('revenues.pos.discount') }}@if ($order->discount_percent !== null) ({{ $quantity($order->discount_percent) }}%)@endif</span><strong>-{{ $money($order->discount) }}</strong></div>
            @endif
            <div class="pos-total pos-grand"><span>{{ __('revenues.pos.net') }}</span><strong>{{ $money($order->net) }} {{ $shift->currency }}</strong></div>
            @if ($order->paid !== null)
                <div class="pos-total"><span>{{ __('revenues.pos.paid') }}</span><strong>{{ $money($order->paid) }}</strong></div>
                <div class="pos-total"><span>{{ __('revenues.pos.change') }}</span><strong>{{ $money(bcsub((string) $order->paid, (string) $order->net, 2)) }}</strong></div>
            @endif

            <p class="pos-thanks">{{ __('revenues.pos.thanks') }}</p>
        </main>
    </body>
</html>
