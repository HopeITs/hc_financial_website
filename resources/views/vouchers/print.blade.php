<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $voucher->id }}</title>
        <style>
            body { font-family: system-ui, "Segoe UI", Tahoma, sans-serif; color: #111; margin: 24px; font-size: 14px; }
            h1 { font-size: 20px; margin: 0 0 4px; }
            .meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 16px 0; }
            .meta span { display: block; font-size: 12px; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            th, td { border: 1px solid #999; padding: 4px 6px; text-align: start; vertical-align: top; }
            th { background: #eee; font-weight: 600; }
            .num { text-align: end; direction: ltr; font-variant-numeric: tabular-nums; }
            .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; margin-top: 56px; }
            .sign div { border-top: 1px solid #111; padding-top: 4px; text-align: center; font-size: 12px; }
            .bar { margin-bottom: 16px; }
            @media print { .bar { display: none; } body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="bar"><button type="button" onclick="window.print()" data-test="print-now">{{ __('vouchers.print') }}</button></div>

        <h1>{{ $voucher->type->label() }} <span dir="ltr">{{ $voucher->id }}</span></h1>
        @if ($voucher->trashed())
            <p data-test="print-rejected">{{ __('vouchers.print_rejected') }}</p>
        @endif
        <p>{{ $voucher->description }}</p>

        <div class="meta">
            <div><span>{{ __('vouchers.date') }}</span><b dir="ltr">{{ $voucher->date->toDateString() }}</b></div>
            <div><span>{{ __('vouchers.status_label') }}</span><b>{{ __('vouchers.status.'.$voucher->status->value) }}</b></div>
            <div><span>{{ __('vouchers.prepared_by') }}</span><b>{{ $names[$voucher->prepared_by] ?? '—' }}</b></div>
            <div><span>{{ __('vouchers.approved_by') }}</span><b>{{ $voucher->approved_by ? ($names[$voucher->approved_by] ?? '—') : '—' }}</b></div>
        </div>

        <table data-test="print-lines">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('vouchers.account') }}</th>
                    <th>{{ __('vouchers.project') }}</th>
                    <th>{{ __('vouchers.tags') }}</th>
                    <th>{{ __('vouchers.currency') }}</th>
                    <th class="num">{{ __('vouchers.debit') }}</th>
                    <th class="num">{{ __('vouchers.credit') }}</th>
                    <th class="num">{{ __('vouchers.debit') }} USD</th>
                    <th class="num">{{ __('vouchers.credit') }} USD</th>
                </tr>
            </thead>
            <tbody>
                @php($debit = '0.00')
                @php($credit = '0.00')
                @foreach ($voucher->lines as $line)
                    @php($debit = bcadd($debit, (string) $line->debit_usd, 2))
                    @php($credit = bcadd($credit, (string) $line->credit_usd, 2))
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td><span dir="ltr">{{ $line->account_code }}</span> {{ $line->account?->localizedName() }}</td>
                        <td dir="ltr">{{ $line->project_code ?? '—' }}</td>
                        <td>{{ collect([$line->budget_line_key, $line->fund_source?->label(), $line->contact, $line->center])->filter()->implode(' · ') ?: '—' }}</td>
                        <td dir="ltr">{{ $line->currency }}</td>
                        <td class="num">{{ number_format((float) $line->debit_orig, 2) }}</td>
                        <td class="num">{{ number_format((float) $line->credit_orig, 2) }}</td>
                        <td class="num">{{ number_format((float) $line->debit_usd, 2) }}</td>
                        <td class="num">{{ number_format((float) $line->credit_usd, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7">{{ __('vouchers.total') }}</th>
                    <th class="num" data-test="print-debit">{{ number_format((float) $debit, 2) }}</th>
                    <th class="num" data-test="print-credit">{{ number_format((float) $credit, 2) }}</th>
                </tr>
            </tfoot>
        </table>

        <div class="sign">
            <div>{{ __('vouchers.prepared_by') }}</div>
            <div>{{ __('vouchers.approved_by') }}</div>
            <div>{{ __('vouchers.received_by') }}</div>
        </div>
    </body>
</html>
