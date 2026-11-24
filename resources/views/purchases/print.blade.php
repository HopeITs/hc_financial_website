@php
    // The forms of the procurement process on the letterhead, with the labels of the Word templates in English and
    // Arabic (lang purchase_print, the same in both languages). The styles are public/css/purchase-forms.css, a plain
    // stylesheet that needs no build.
    $t = fn (string $key, array $replace = []) => __('purchase_print.'.$key, $replace);
    $day = fn ($date): string => $date?->format('d/m/Y') ?? '';
    $quantity = fn ($value): string => rtrim(rtrim((string) $value, '0'), '.');
    $money = fn ($value): string => $value === null || $value === '' ? '' : number_format((float) $value, 2);
    $supplier = $purchase->awardedQuote;
    $rows = max(10, $purchase->items->count());
    $preparer = $names[$purchase->created_by] ?? '';
    $approvers = collect($purchase->approvals ?? [])->map(fn (array $step): string => $names[(int) $step['user']] ?? $step['name'])->implode(' / ');
    $approvedOn = $purchase->approved_at?->format('d/m/Y') ?? '';
    $sign = fn (string $name = '', string $title = '', string $date = ''): string => $t('name').' '.$name."\n".$t('position').' '.$title."\n".$t('signature')."\n".$t('date').' '.$date;
    $bidders = array_slice($comparison['quotes'], 0, 4);
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $t('titles.'.$form) }} · {{ $purchase->number(strtoupper($form)) }}</title>
        @vite(['resources/css/app.css'])
        <link rel="stylesheet" href="{{ asset('css/purchase-forms.css') }}">
    </head>
    <body class="pf-sheet" data-test="purchase-print-{{ $form }}">
        <div class="pf-bar">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ __('purchases.print_now') }}</x-ui.button>
        </div>

        <section class="pf-page">
            <img class="pf-bg" src="{{ asset('images/micro-forms/letterhead.jpg') }}" alt="">
            <div class="pf-body">
                <h1 class="pf-title">{{ $t('titles.'.$form) }}</h1>

                @switch($form)
                    @case('pr')
                        <table class="pf-table">
                            <tr><td class="pf-label">{{ $t('pr_number') }}</td><td>{{ $purchase->number('PR') }}</td><td class="pf-label">{{ $t('project') }}</td><td>{{ $purchase->project_code }} · {{ $purchase->project?->name }}</td></tr>
                            <tr><td class="pf-label">{{ $t('request_date') }}</td><td>{{ $day($purchase->created_at) }}</td><td class="pf-label">{{ $t('budget_line') }}</td><td>{{ $purchase->items->map(fn ($item) => $item->budgetLine?->name ?? $item->budget_line_key)->unique()->implode(', ') }}</td></tr>
                            <tr><td class="pf-label">{{ $t('location') }}</td><td>{{ $purchase->location }}</td><td class="pf-label">{{ $t('pr_description') }}</td><td>{{ $purchase->title }}</td></tr>
                        </table>
                        <table class="pf-table pf-gap">
                            <thead><tr><th class="pf-small">{{ $t('no') }}</th><th>{{ $t('description') }}</th><th class="pf-mid">{{ $t('unit') }}</th><th class="pf-mid">{{ $t('qty') }}</th><th class="pf-mid">{{ $t('est_price') }}</th><th class="pf-mid">{{ $t('est_total') }}</th><th class="pf-wide">{{ $t('remarks') }}</th></tr></thead>
                            <tbody>
                                @for ($i = 0; $i < $rows; $i++)
                                    @php($item = $purchase->items[$i] ?? null)
                                    <tr>
                                        <td class="pf-center">{{ $i + 1 }}</td>
                                        <td>{{ $item?->description }}</td>
                                        <td class="pf-center">{{ $item?->unit }}</td>
                                        <td class="pf-num">{{ $item ? $quantity($item->quantity) : '' }}</td>
                                        <td class="pf-num">{{ $item ? $money($item->estimated_price) : '' }}</td>
                                        <td class="pf-num">{{ $item ? $money((float) $item->quantity * (float) $item->estimated_price) : '' }}</td>
                                        <td>{{ $item?->remarks }}</td>
                                    </tr>
                                @endfor
                                <tr><td colspan="5" class="pf-head">{{ $t('total') }} ({{ $purchase->currency }})</td><td class="pf-num pf-strong">{{ $money($estimated) }}</td><td></td></tr>
                            </tbody>
                        </table>
                        <table class="pf-table pf-signs pf-gap">
                            <thead><tr><th>{{ $t('requested_by') }}</th><th>{{ $t('reviewed_by') }}</th><th>{{ $t('approved_by') }}</th></tr></thead>
                            <tbody><tr><td>{{ $sign($preparer, '', $day($purchase->created_at)) }}</td><td>{{ $sign() }}</td><td>{{ $sign() }}</td></tr></tbody>
                        </table>
                        @break

                    @case('rfq')
                        <table class="pf-table">
                            <tr><td class="pf-label">{{ $t('rfq_number') }}</td><td>{{ $purchase->number('RFQ') }}</td><td class="pf-label">{{ $t('project') }}</td><td>{{ $purchase->project_code }} · {{ $purchase->project?->name }}</td></tr>
                            <tr><td class="pf-label">{{ $t('request_date') }}</td><td>{{ $day(now()) }}</td><td class="pf-label">{{ $t('budget_code') }}</td><td>{{ $purchase->items->map(fn ($item) => $item->budgetLine?->name ?? $item->budget_line_key)->unique()->implode(', ') }}</td></tr>
                            <tr><td class="pf-label">{{ $t('location') }}</td><td>{{ $purchase->location }}</td><td class="pf-label">{{ $t('delivery') }}</td><td>{{ $day($purchase->needed_by) }} {{ $purchase->delivery_place }}</td></tr>
                            <tr><td class="pf-label">{{ $t('contact') }}</td><td>{{ $preparer }}</td><td class="pf-label">{{ $t('currency') }}</td><td>{{ $purchase->currency }}</td></tr>
                        </table>
                        <p class="pf-text">{{ $t('rfq_note_en') }}<span class="pf-ar">{{ $t('rfq_note_ar') }}</span></p>
                        <table class="pf-table">
                            <thead><tr><th class="pf-small">{{ $t('no') }}</th><th>{{ $t('description') }}</th><th class="pf-mid">{{ $t('unit') }}</th><th class="pf-mid">{{ $t('qty') }}</th><th class="pf-mid">{{ $t('unit_price') }}</th><th class="pf-mid">{{ $t('line_total') }}</th><th class="pf-mid">{{ $t('brand') }}</th><th class="pf-mid">{{ $t('remarks') }}</th></tr></thead>
                            <tbody>
                                @for ($i = 0; $i < $rows; $i++)
                                    @php($item = $purchase->items[$i] ?? null)
                                    <tr><td class="pf-center">{{ $i + 1 }}</td><td>{{ $item?->description }}</td><td class="pf-center">{{ $item?->unit }}</td><td class="pf-num">{{ $item ? $quantity($item->quantity) : '' }}</td><td></td><td></td><td></td><td></td></tr>
                                @endfor
                            </tbody>
                        </table>
                        <table class="pf-table">
                            <tr><td class="pf-head" colspan="2">{{ $t('supplier') }}</td></tr>
                            @foreach (['name', 'address', 'date', 'validity', 'stamp'] as $field)
                                <tr><td class="pf-label">{{ $t('supplier_'.$field) }}</td><td></td></tr>
                            @endforeach
                        </table>
                        @break

                    @case('cba')
                        <table class="pf-table">
                            <tr><td class="pf-label">{{ $t('pr_reference') }}</td><td>{{ $purchase->number('PR') }} · {{ $purchase->title }}</td></tr>
                        </table>
                        <table class="pf-table">
                            <thead>
                                <tr>
                                    <th class="pf-small" rowspan="3">{{ $t('no') }}</th><th rowspan="3">{{ $t('description') }}</th><th class="pf-small" rowspan="3">{{ $t('unit') }}</th><th class="pf-small" rowspan="3">{{ $t('qty') }}</th>
                                    @foreach ($bidders as $index => $row)
                                        <th colspan="2">{{ $t('bidder', ['number' => $index + 1]) }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($bidders as $row)
                                        <th colspan="2">{{ $row['quote']->supplier }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($bidders as $row)
                                        <th>{{ $t('unit_cost') }}</th><th>{{ $t('line_total') }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchase->items as $item)
                                    <tr>
                                        <td class="pf-center">{{ $item->line_no }}</td><td>{{ $item->description }}</td><td class="pf-center">{{ $item->unit }}</td><td class="pf-num">{{ $quantity($item->quantity) }}</td>
                                        @foreach ($bidders as $row)
                                            <td class="pf-num">{{ $money($row['prices'][$item->id]['price'] ?? null) }}</td><td class="pf-num">{{ $money($row['prices'][$item->id]['total'] ?? null) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="4" class="pf-head">{{ $t('total_cost') }} ({{ $purchase->currency }})</td>
                                    @foreach ($bidders as $row)
                                        <td></td><td class="pf-num pf-strong">{{ $money($row['total']) }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                        <table class="pf-table">
                            <tr><td class="pf-label pf-wide">{{ $t('recommended') }}</td><td>{{ $supplier?->supplier }}</td></tr>
                            <tr><td class="pf-label pf-wide">{{ $t('reason') }}</td><td>{{ $purchase->award_reason }}</td></tr>
                        </table>
                        <table class="pf-table pf-signs">
                            <thead><tr><th>{{ $t('committee', ['number' => 1]) }}</th><th>{{ $t('committee', ['number' => 2]) }}</th><th>{{ $t('committee', ['number' => 3]) }}</th></tr></thead>
                            <tbody><tr><td>{{ $sign() }}</td><td>{{ $sign() }}</td><td>{{ $sign() }}</td></tr></tbody>
                        </table>
                        @break

                    @case('po')
                        <table class="pf-table">
                            <tr><td class="pf-label">{{ $t('po_number') }}</td><td>{{ $purchase->number('PO') }}</td><td class="pf-label">{{ $t('date') }}</td><td>{{ $day($purchase->ordered_on) }}</td></tr>
                            <tr><td class="pf-label">{{ $t('pr_ref') }}</td><td>{{ $purchase->number('PR') }}</td><td class="pf-label">{{ $t('currency') }}</td><td>{{ $purchase->currency }}</td></tr>
                            <tr><td class="pf-label">{{ $t('supplier_name') }}</td><td>{{ $supplier?->supplier }}</td><td class="pf-label">{{ $t('supplier_address') }}</td><td>{{ $supplier?->address }}</td></tr>
                            <tr><td class="pf-label">{{ $t('phone') }}</td><td>{{ $supplier?->phone }}</td><td class="pf-label">{{ $t('delivery_date') }}</td><td>{{ $day($purchase->needed_by) }}</td></tr>
                        </table>
                        <table class="pf-table">
                            <thead><tr><th class="pf-small">{{ $t('no') }}</th><th class="pf-wide">{{ $t('budget_line') }}</th><th>{{ $t('description') }}</th><th class="pf-mid">{{ $t('unit') }}</th><th class="pf-mid">{{ $t('qty') }}</th><th class="pf-mid">{{ $t('unit_price') }}</th><th class="pf-mid">{{ $t('line_total') }}</th></tr></thead>
                            <tbody>
                                @for ($i = 0; $i < $rows; $i++)
                                    @php($line = $orderLines[$i] ?? null)
                                    <tr>
                                        <td class="pf-center">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $line ? ($line['item']->budgetLine?->name ?? $line['item']->budget_line_key) : '' }}</td>
                                        <td>{{ $line['item']->description ?? '' }}</td>
                                        <td class="pf-center">{{ $line['item']->unit ?? '' }}</td>
                                        <td class="pf-num">{{ $line ? $quantity($line['item']->quantity) : '' }}</td>
                                        <td class="pf-num">{{ $line ? $money($line['price']) : '' }}</td>
                                        <td class="pf-num">{{ $line ? $money($line['total']) : '' }}</td>
                                    </tr>
                                @endfor
                                <tr><td colspan="6" class="pf-head">{{ $t('subtotal') }}</td><td class="pf-num">{{ $money($orderTotal !== null ? bcsub($orderTotal, (string) $purchase->other_charges, 2) : null) }}</td></tr>
                                <tr><td colspan="6" class="pf-head">{{ $t('other_charges') }}</td><td class="pf-num">{{ $money($purchase->other_charges) }}</td></tr>
                                <tr><td colspan="6" class="pf-head">{{ $t('grand_total') }}</td><td class="pf-num pf-strong">{{ $money($orderTotal) }}</td></tr>
                            </tbody>
                        </table>
                        <table class="pf-table pf-signs">
                            <thead><tr><th>{{ $t('prepared_by') }}</th><th>{{ $t('approved_by') }}</th></tr></thead>
                            <tbody><tr><td>{{ $sign($preparer) }}</td><td>{{ $sign($approvers, '', $approvedOn) }}</td></tr></tbody>
                        </table>
                        <table class="pf-table pf-signs">
                            <thead><tr><th colspan="2">{{ $t('supplier_acceptance') }}</th></tr></thead>
                            <tbody><tr><td colspan="2">{{ $t('name').' '.($supplier?->supplier ?? '')."\n".$t('date')."\n".$t('supplier_stamp') }}</td></tr></tbody>
                        </table>
                        @break

                    @case('grn')
                    @case('srn')
                        @php($service = $form === 'srn')
                        <table class="pf-table">
                            <tr><td class="pf-label">{{ $t($form.'_number') }}</td><td>{{ $purchase->number(strtoupper($form)) }}</td><td class="pf-label">{{ $t('received_on') }}</td><td>{{ $day($purchase->received_on) }}</td></tr>
                            <tr><td class="pf-label">{{ $t('supplier_name') }}</td><td>{{ $supplier?->supplier }}</td><td class="pf-label">{{ $t('po_no') }}</td><td>{{ $purchase->number('PO') }}</td></tr>
                            <tr><td class="pf-label">{{ $t('receiving_location') }}</td><td>{{ $purchase->receiving_location }}</td><td class="pf-label">{{ $t('invoice_number') }}</td><td>{{ $purchase->invoice_number }}</td></tr>
                        </table>
                        <table class="pf-table">
                            @if ($service)
                                <thead><tr><th class="pf-mid">{{ $t('pr_no') }}</th><th class="pf-small">{{ $t('item_no') }}</th><th>{{ $t('service_description') }}</th><th class="pf-mid">{{ $t('expected_on') }}</th><th class="pf-mid">{{ $t('completed_on') }}</th><th class="pf-mid">{{ $t('quality') }}</th><th class="pf-mid">{{ $t('comments') }}</th></tr></thead>
                            @else
                                <thead><tr><th class="pf-mid">{{ $t('pr_no') }}</th><th class="pf-small">{{ $t('item_no') }}</th><th>{{ $t('goods_description') }}</th><th class="pf-mid">{{ $t('unit') }}</th><th class="pf-mid">{{ $t('expected_qty') }}</th><th class="pf-mid">{{ $t('received_qty') }}</th><th class="pf-wide">{{ $t('condition') }}</th></tr></thead>
                            @endif
                            <tbody>
                                @foreach ($purchase->items as $item)
                                    <tr>
                                        <td class="pf-center">{{ $purchase->number('PR') }}</td>
                                        <td class="pf-center">{{ $item->line_no }}</td>
                                        <td>{{ $item->description }}</td>
                                        @if ($service)
                                            <td class="pf-center">{{ $day($item->expected_on) }}</td><td class="pf-center">{{ $day($item->completed_on) }}</td><td>{{ $item->condition }}</td><td>{{ $item->remarks }}</td>
                                        @else
                                            <td class="pf-center">{{ $item->unit }}</td><td class="pf-num">{{ $quantity($item->quantity) }}</td><td class="pf-num">{{ $quantity($item->received_quantity) }}</td><td>{{ $item->condition }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <table class="pf-table">
                            <tr><td class="pf-label pf-wide">{{ $t('remarks') }}</td><td>{{ $purchase->receipt_remarks }}</td></tr>
                            <tr><td>{{ $t('acceptable') }}</td><td class="pf-center pf-strong">{{ $purchase->accepted ? $t('yes') : $t('no_answer') }}</td></tr>
                        </table>
                        <p class="pf-text">{{ $t('received_note') }}</p>
                        <table class="pf-table pf-signs">
                            <thead><tr><th>{{ $t('logistic') }}</th><th>{{ $t('requested') }}</th></tr></thead>
                            <tbody><tr><td>{{ $t('name')."\n".$t('signature') }}</td><td>{{ $t('name').' '.$preparer."\n".$t('signature') }}</td></tr></tbody>
                        </table>
                        <table class="pf-table pf-signs">
                            <thead><tr><th>{{ $t('supplier_acceptance') }}</th></tr></thead>
                            <tbody><tr><td>{{ $t('name').' '.($supplier?->supplier ?? '')."\n".$t('signature')."\n".$t('date')."\n".$t('stamp') }}</td></tr></tbody>
                        </table>
                        @break

                    @case('rdp')
                        <table class="pf-table">
                            <tr><td class="pf-label">{{ $t('rdp_number') }}</td><td>{{ $purchase->number('RDP') }}</td><td class="pf-label">{{ $t('to') }}</td><td>{{ $t('finance_department') }}</td></tr>
                            <tr><td class="pf-label">{{ $t('request_date') }}</td><td>{{ $day($purchase->received_on) }}</td><td class="pf-label">{{ $t('from') }}</td><td>{{ $t('logistics_department') }}</td></tr>
                        </table>
                        <p class="pf-text">{{ $t('rdp_note') }}</p>
                        <table class="pf-table">
                            <tr><td class="pf-label pf-wide">{{ $t('total_amount') }}</td><td class="pf-strong">{{ $money($purchase->invoice_amount) }} {{ $purchase->currency }}</td></tr>
                            <tr><td class="pf-label pf-wide">{{ $t('total_usd') }}</td><td>{{ $money($orderUsd) }} USD</td></tr>
                            <tr><td class="pf-label pf-wide">{{ $t('purpose') }}</td><td>{{ $purchase->title }} · {{ $purchase->project_code }}</td></tr>
                            <tr><td class="pf-label pf-wide">{{ $t('po_number') }}</td><td>{{ $purchase->number('PO') }}</td></tr>
                            <tr><td class="pf-label pf-wide">{{ $t('supplier') }}</td><td>{{ $supplier?->supplier }}</td></tr>
                        </table>
                        <table class="pf-table pf-signs">
                            <thead><tr><th>{{ $t('requested_by') }}</th><th>{{ $t('reviewed_by') }}</th><th>{{ $t('authorized_by') }}</th></tr></thead>
                            <tbody><tr><td>{{ $sign($preparer) }}</td><td>{{ $sign() }}</td><td>{{ $sign() }}</td></tr></tbody>
                        </table>
                        @break
                @endswitch
            </div>
        </section>
    </body>
</html>
