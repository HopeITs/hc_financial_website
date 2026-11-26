@php
    // A shift's end-of-day report (finance request of 2026-11-27); the styles are public/css/pos-print.css.
    $money = fn ($value): string => number_format((float) $value, 2);
    $quantity = fn ($value): string => rtrim(rtrim((string) $value, '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('revenues.pos.day_end') }} · {{ $shift->project_code }} · {{ $shift->day->format('d/m/Y') }}</title>
        @vite(['resources/css/app.css'])
        <link rel="stylesheet" href="{{ asset('css/pos-print.css') }}">
    </head>
    <body class="pos-paper" data-test="pos-report">
        <div class="pos-bar">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ __('revenues.pos.print') }}</x-ui.button>
        </div>

        <main class="pos-sheet">
            <img class="pos-logo" src="{{ asset('logo.png') }}" alt="">
            <p class="pos-title">{{ __('revenues.pos.day_end') }} · {{ $project?->name ?? $shift->project_code }}</p>

            <dl class="pos-meta">
                <dt>{{ __('revenues.day') }}</dt><dd dir="ltr">{{ $shift->day->format('Y-m-d') }}</dd>
                <dt>{{ __('revenues.received_in') }}</dt><dd dir="ltr">{{ $shift->cash_account }} ({{ $shift->currency }})</dd>
                <dt>{{ __('revenues.pos.cashier') }}</dt><dd>{{ $cashier }}</dd>
                <dt>{{ __('revenues.pos.state') }}</dt><dd>{{ $shift->isOpen() ? __('revenues.pos.state_open') : __('revenues.pos.state_closed', ['voucher' => $shift->voucher_id]) }}</dd>
                <dt>{{ __('revenues.pos.orders') }}</dt><dd>{{ $report['orders'] }}@if ($report['open'] > 0) · {{ __('revenues.pos.still_open', ['count' => $report['open']]) }}@endif</dd>
            </dl>

            <div class="pos-total"><span>{{ __('revenues.pos.gross') }}</span><strong>{{ $money($report['gross']) }}</strong></div>
            <div class="pos-total"><span>{{ __('revenues.pos.discount') }}</span><strong>-{{ $money($report['discount']) }}</strong></div>
            <div class="pos-total pos-grand"><span>{{ __('revenues.pos.net') }}</span><strong>{{ $money($report['net']) }} {{ $shift->currency }}</strong></div>

            <h2 class="pos-title">{{ __('revenues.pos.by_item') }}</h2>
            <table class="pos-table">
                <thead><tr><th>{{ __('revenues.item_name') }}</th><th class="num">{{ __('revenues.quantity') }}</th><th class="num">{{ __('revenues.amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($report['items'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="num">{{ $quantity($row['quantity']) }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            <h2 class="pos-title">{{ __('revenues.pos.by_waiter') }}</h2>
            <table class="pos-table">
                <tbody>
                    @foreach ($report['waiters'] as $waiter => $amount)
                        <tr><td>{{ $waiter !== '' ? $waiter : __('revenues.pos.no_waiter') }}</td><td class="num">{{ $money($amount) }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            <h2 class="pos-title">{{ __('revenues.pos.by_service') }}</h2>
            <table class="pos-table">
                <tbody>
                    @foreach ($report['services'] as $service => $amount)
                        <tr><td>{{ __('revenues.pos.services.'.$service) }}</td><td class="num">{{ $money($amount) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </main>
    </body>
</html>
