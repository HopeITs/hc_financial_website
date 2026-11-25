@php
    // Each form is its original page (public/images/micro-forms) with the file's values set on its lines; the places
    // are in public/css/micro-forms.css, a plain stylesheet that needs no build. The forms print in Arabic; the delivery
    // protocol adds the English lines.
    $ar = fn (string $key, array $replace = []) => __('micro_print.'.$key, $replace, 'ar');
    $en = fn (string $key, array $replace = []) => __('micro_print.'.$key, $replace, 'en');
    $page = fn (string $image) => asset('images/micro-forms/'.$image.'.jpg');
    $name = $file->beneficiary;
    $funder = $file->project?->donor ?? $file->funder ?? '';
    $quantity = fn ($value): string => rtrim(rtrim((string) $value, '0'), '.');
    $day = fn ($date): string => $date?->format('d/m/Y') ?? '';
    $managers = collect($file->approvals ?? [])->mapWithKeys(fn (array $step): array => [(int) $step['role'] => $names[(int) $step['user']] ?? $step['name']]);
    $programManager = $managers[\App\Enums\RoleId::ProgramManager->value] ?? '';
    $financeManager = $managers[\App\Enums\RoleId::FinancialManager->value] ?? '';
    $purchasing = (string) ($file->purchases->firstWhere('kind', 'advance')?->person ?? '');
    // The rows a form has no room for go on a page of their own, on the letterhead.
    $rooms = ['order' => 7, 'purchase' => 15, 'quote' => 11];
    $rows = match ($form) {
        'order' => $invoices,
        'purchase', 'quote' => $file->items,
        default => collect(),
    };
    $shown = isset($rooms[$form]) ? $rows->take($rooms[$form]) : $rows;
    $rest = isset($rooms[$form]) ? $rows->slice($rooms[$form])->values() : collect();
    $cell = 'mf-cell';
    // The delivery protocol (finance form of 2026-11-28). Kept in this first block: Blade cannot read a PHP block that
    // comes after a one-line PHP statement, as the purchase list's below.
    $donorEn = $donor?->name_en ?? $funder;
    $donorAr = $donor?->name_ar ?? $funder;
    $pounds = $file->currency === 'SYP';
    $cost = fn ($amount): string => $pounds ? \App\Support\Money::normalize(bcdiv(\App\Support\Money::normalize($amount), \App\Support\Money::NEW_SYP_DIVISOR, 4)) : \App\Support\Money::normalize($amount);
    $costCurrency = $pounds ? 'SYP_NEW' : $file->currency;
    $label = fn (string $currency, string $locale): string => __('micro_print.currency.'.$currency, [], $locale);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $ar('titles.'.$form) }} · {{ $name }}</title>
        @vite(['resources/css/app.css'])
        <link rel="stylesheet" href="{{ asset('css/micro-forms.css') }}">
    </head>
    <body class="mf-sheet" data-test="micro-print-{{ $form }}">
        <div class="mf-bar">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ $ar('print') }}</x-ui.button>
        </div>

        @if ($form === 'order')
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('order') }}" alt="">
                <span class="mf-field mf-order-date" dir="ltr">{{ $day($file->submitted_at ?? $file->created_at) }}</span>
                <span class="mf-field mf-order-number" dir="ltr">{{ $file->id }}</span>
                <span class="mf-field mf-order-name">{{ $name }}</span>
                <span class="mf-field mf-order-name-en mf-ltr">{{ $file->name_en }}</span>
                <span class="mf-field mf-order-project">{{ $file->profession }}</span>
                <span class="mf-field mf-order-number-project" dir="ltr">{{ $file->project_number }}</span>
                <span class="mf-field mf-order-mobile" dir="ltr">{{ $file->mobile }}</span>
                <span class="mf-field mf-order-national" dir="ltr">{{ $file->national_number }}</span>
                <span class="mf-field mf-order-donor">{{ $funder }}</span>
                <span class="mf-field mf-order-first-due" dir="ltr">{{ $day($file->first_payment_date) }}</span>
                <span class="mf-field mf-order-requested"><x-money :amount="$file->requested_amount" :currency="$file->currency" /></span>
                <span class="mf-field mf-order-seizure"><x-money :amount="$file->seizure_fee" :currency="$file->currency" /></span>
                <span class="mf-field mf-order-percent" dir="ltr">{{ $quantity($file->recovery_percent) }}%</span>
                <span class="mf-field mf-order-instalment"><x-money :amount="$file->instalment_amount" :currency="$file->currency" /></span>
                <span class="mf-field mf-center mf-order-sign-manager">{{ $programManager }}</span>
                <span class="mf-field mf-center mf-order-sign-finance">{{ $financeManager }}</span>
                @if ((float) $file->extra_amount > 0)
                    <span class="mf-field mf-order-extra"><x-money :amount="$file->extra_amount" /></span>
                    <span class="mf-field mf-order-extra-reason">{{ $file->extra_reason }}</span>
                @endif
                <div class="mf-order-invoices" data-test="print-invoices">
                    @foreach ($shown as $invoice)
                        <span class="{{ $cell }}"><x-money :amount="$invoice->amount" :currency="$invoice->currency" /></span>
                        <span class="{{ $cell }}" dir="ltr">{{ $invoice->number }}</span>
                        <span class="{{ $cell }}">{{ $invoice->description_ar }}</span>
                        <span class="{{ $cell }}">{{ $invoice->merchant }}@if ($invoice->is_seizure) · {{ $ar('seizure_invoice') }}@endif</span>
                    @endforeach
                </div>
                @if ($invoices->isNotEmpty())
                    <span class="mf-field mf-order-total"><x-money :amount="$totals['invoiced']" :currency="$file->currency" /></span>
                @endif
                <span class="mf-field mf-center mf-order-sign-2-manager">{{ $programManager }}</span>
                <span class="mf-field mf-center mf-order-sign-2-purchasing">{{ $purchasing }}</span>
                <span class="mf-field mf-center mf-order-sign-2-finance">{{ $financeManager }}</span>
            </section>
        @elseif ($form === 'purchase')
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('purchase') }}" alt="">
                <span class="mf-field mf-purchase-number" dir="ltr">{{ $file->project_number }}</span>
                <span class="mf-field mf-purchase-name">{{ $name }}</span>
                <span class="mf-field mf-purchase-mobile" dir="ltr">{{ $file->mobile }}</span>
                <span class="mf-field mf-purchase-profession">{{ $file->profession }}</span>
                <span class="mf-field mf-purchase-area">{{ $file->address }}</span>
                <div class="mf-purchase-items" data-test="print-items">
                    @foreach (['no', 'item', 'kind', 'quantity', 'condition'] as $heading)
                        <span class="mf-head">{{ $ar($heading) }}</span>
                    @endforeach
                    @for ($index = 0; $index < 15; $index++)
                        @php($item = $shown[$index] ?? null)
                        <span @class(['mf-mid', 'mf-shade' => $index % 2 === 1])>{{ $index + 1 }}</span>
                        <span @class(['mf-shade' => $index % 2 === 1])>{{ $item?->name }}</span>
                        <span @class(['mf-shade' => $index % 2 === 1])>{{ $item?->kind }}</span>
                        <span @class(['mf-mid', 'mf-shade' => $index % 2 === 1]) dir="ltr">{{ $item !== null ? $quantity($item->quantity) : '' }}</span>
                        <span @class(['mf-shade' => $index % 2 === 1])>{{ $item !== null ? $ar('conditions.'.$item->condition) : '' }}</span>
                    @endfor
                </div>
                <div class="mf-purchase-text">
                    <p>{{ $ar('pledges_title') }}</p>
                    <ul class="mf-list">
                        @foreach (__('micro_print.purchase_pledges', [], 'ar') as $pledge)
                            <li>{{ $pledge }}</li>
                        @endforeach
                    </ul>
                    <p>{{ $ar('beneficiary_sign') }}</p>
                </div>
                <div class="mf-purchase-signs">
                    <span>{{ $ar('micro_manager') }}</span>
                    <span>{{ $ar('financial_manager') }}</span>
                    <span>{{ $ar('purchasing_officer') }}</span>
                </div>
            </section>
        @elseif ($form === 'quote')
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('quote') }}" alt="">
                <span class="mf-field mf-quote-for">{{ $name }}</span>
                <div class="mf-quote-items" data-test="print-items">
                    @foreach ($shown as $item)
                        <span class="{{ $cell }}">{{ $item->name }}</span>
                        <span class="{{ $cell }}">{{ $item->kind }}</span>
                        <span class="{{ $cell }} mf-mid" dir="ltr">{{ $quantity($item->quantity) }}</span>
                        <span></span>
                        <span></span>
                        <span></span>
                    @endforeach
                </div>
            </section>
        @elseif ($form === 'bond')
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('bond') }}" alt="">
                <span class="mf-field mf-bond-name">{{ $name }}</span>
                <span class="mf-field mf-bond-national" dir="ltr">{{ $file->national_number }}</span>
                <span class="mf-field mf-bond-amount"><x-money :amount="$total" :currency="$file->currency" /></span>
                <span class="mf-field mf-bond-words">{{ $words['ar'] }}</span>
            </section>
        @elseif ($form === 'undertaking')
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('undertaking') }}" alt="">
                <div class="mf-undertaking-box">
                <table class="mf-table" data-test="print-invoices">
                    <thead>
                        <tr>
                            <th>{{ $ar('merchant') }}</th>
                            <th>{{ $ar('invoice_number') }}</th>
                            <th>{{ $ar('materials') }}</th>
                            <th>{{ $ar('amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->merchant }}</td>
                                <td dir="ltr">{{ $invoice->number }}</td>
                                <td>{{ $invoice->description_ar }}</td>
                                <td><x-money :amount="$invoice->amount" :currency="$invoice->currency" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <span class="mf-field mf-undertaking-name">{{ $name }}</span>
                <span class="mf-field mf-undertaking-date" dir="ltr">{{ $day($file->protocol_signed_on) }}</span>
            </section>
        @elseif ($form === 'card')
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('card-front') }}" alt="">
                @foreach ([false, true] as $turned)
                    <div @class(['mf-turned' => $turned])>
                        @foreach ([1, 2] as $copy)
                            <span class="mf-field mf-card-name-{{ $copy }}">{{ $name }}</span>
                            <span class="mf-field mf-card-project-{{ $copy }}" dir="ltr">{{ $file->project_number }}</span>
                            <span class="mf-field mf-card-donor-{{ $copy }}">{{ $funder }}</span>
                        @endforeach
                    </div>
                @endforeach
            </section>
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('card-inside') }}" alt="">
            </section>
        @elseif ($form === 'protocol')
            {{-- The delivery protocol on the organisation's own form (finance form of 2026-11-28): the donor's logo, the
                 title and Hope's logo at the top, English on the left and Arabic on the right. Pounds are declared in
                 old and new pounds, and the items cost in new pounds (the values are set in the block at the top). --}}
            <section class="mf-page dp-page" data-test="delivery-protocol">
                <header class="dp-head" dir="ltr">
                    <div class="dp-logo">
                        @if ($donor?->logoUrl() !== null)
                            <img src="{{ $donor->logoUrl() }}" alt="{{ $donorEn }}" data-test="protocol-donor-logo">
                        @endif
                    </div>
                    <h1 class="dp-title">{{ $en('titles.protocol_form') }}<br><span dir="rtl">{{ $ar('titles.protocol') }}</span></h1>
                    <div class="dp-logo dp-logo-end"><img src="{{ asset('images/hope-logo.png') }}" alt="Hope Center"></div>
                </header>

                <table class="dp-table dp-declare" dir="ltr">
                    <tr>
                        <td class="dp-en">{{ $en('undersigned') }}</td>
                        <td class="dp-val" colspan="2">{{ $file->name_en }}</td>
                        <td class="dp-val" colspan="2" dir="rtl">{{ $name }}</td>
                        <td class="dp-ar" dir="rtl">{{ $ar('undersigned') }}</td>
                    </tr>
                    <tr>
                        <td class="dp-en">{{ $en('national_number_form') }}</td>
                        <td class="dp-val" colspan="2">{{ $file->national_number }}</td>
                        <td class="dp-val" colspan="2">{{ $file->national_number }}</td>
                        <td class="dp-ar" dir="rtl">{{ $ar('national_number') }}</td>
                    </tr>
                    @foreach ($delivered as $row)
                        <tr data-test="protocol-total-{{ $row['currency'] }}">
                            <td class="dp-en">{{ $en('declare_received') }}</td>
                            <td class="dp-num"><x-money :amount="$row['amount']" /></td>
                            <td class="dp-cur">{{ $label($row['currency'], 'en') }}</td>
                            <td class="dp-cur" dir="rtl">{{ $label($row['currency'], 'ar') }}</td>
                            <td class="dp-num"><x-money :amount="$row['amount']" /></td>
                            <td class="dp-ar" dir="rtl">{{ $ar('declare_received') }}</td>
                        </tr>
                        <tr>
                            <td class="dp-words" colspan="3"><u>{{ $en('in_writing') }}</u> {{ $row['en'] }}</td>
                            <td class="dp-words" colspan="3" dir="rtl"><u>{{ $ar('in_writing') }}</u> {{ $row['ar'] }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="dp-words" colspan="3">{{ $en('use_properly', ['donor' => $donorEn]) }}</td>
                        <td class="dp-words" colspan="3" dir="rtl">{{ $ar('use_properly', ['donor' => $donorAr]) }}</td>
                    </tr>
                </table>

                <table class="dp-table dp-items" dir="ltr" data-test="print-invoices">
                    <thead>
                        <tr>
                            <th>{{ $en('item') }}<br><span dir="rtl">{{ $ar('item') }}</span></th>
                            <th>{{ $en('cost') }}<br><span dir="rtl">{{ $ar('cost') }}</span></th>
                            <th>{{ $en('invoice_number') }}<br><span dir="rtl">{{ $ar('invoice_number') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="dp-item">
                                    {{ $invoice->description_en }}@if ($invoice->is_seizure) ({{ $en('seizure_invoice') }})@endif
                                    <br><span dir="rtl">{{ $invoice->description_ar }}@if ($invoice->is_seizure) ({{ $ar('seizure_invoice') }})@endif</span>
                                </td>
                                <td class="dp-num"><x-money :amount="$cost($invoice->loan_amount)" /></td>
                                <td class="dp-mid">{{ $en('invoice') }} {{ $invoice->number }}</td>
                            </tr>
                        @endforeach
                        <tr class="dp-total">
                            <td>{{ $ar('total') }} / {{ $en('total') }}</td>
                            <td class="dp-num"><x-money :amount="$cost($totals['invoiced'])" /> <span class="dp-small">{{ $label($costCurrency, 'en') }}</span></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="dp-row" dir="ltr">
                    <p class="dp-strong">{{ $en('lawyer') }}</p>
                    <p class="dp-strong" dir="rtl">{{ $ar('lawyer') }}</p>
                </div>
                <div class="dp-row dp-signs" dir="ltr">
                    <div class="dp-sign">
                        <div class="dp-strong">{{ $en('date') }} / {{ $ar('date') }}</div>
                        <div>{{ $day($file->protocol_signed_on ?? now()) }}</div>
                    </div>
                    <div class="dp-sign">
                        <div class="dp-strong">{{ $en('signature') }} / {{ $ar('signature') }}</div>
                        <div class="dp-line"></div>
                    </div>
                </div>

                <footer class="dp-foot" dir="ltr">{{ $en('website') }}</footer>
            </section>
        @endif

        {{-- What a form has no room for, on a page of its own --}}
        @if ($rest->isNotEmpty())
            <section class="mf-page" data-test="print-annex">
                <img class="mf-bg" src="{{ $page('letterhead') }}" alt="">
                <div class="mf-body">
                    <h2 class="mf-title">{{ $ar('annex', ['form' => $ar('titles.'.$form)]) }}</h2>
                    <table class="mf-table">
                        <tbody>
                            @foreach ($rest as $index => $row)
                                <tr>
                                    <td>{{ $rooms[$form] + $index + 1 }}</td>
                                    @if ($form === 'order')
                                        <td><x-money :amount="$row->amount" :currency="$row->currency" /></td>
                                        <td dir="ltr">{{ $row->number }}</td>
                                        <td>{{ $row->description_ar }}</td>
                                        <td>{{ $row->merchant }}</td>
                                    @else
                                        <td>{{ $row->name }}</td>
                                        <td>{{ $row->kind }}</td>
                                        <td dir="ltr">{{ $quantity($row->quantity) }}</td>
                                        <td>{{ $ar('conditions.'.$row->condition) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </body>
</html>
