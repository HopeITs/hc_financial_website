@php
    // Each form is its original page (public/images/micro-forms) with the file's values set on its lines; the places
    // are in resources/css/micro-forms.css. The forms print in Arabic; the delivery protocol adds the English lines.
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
    $cell = 'overflow-hidden whitespace-nowrap px-1.5';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $ar('titles.'.$form) }} · {{ $name }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-secondary-soft py-6 print:bg-white print:py-0" data-test="micro-print-{{ $form }}">
        <div class="mf-bar print:hidden">
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
                        <span @class(['justify-center', 'mf-shade' => $index % 2 === 1])>{{ $index + 1 }}</span>
                        <span @class(['mf-shade' => $index % 2 === 1])>{{ $item?->name }}</span>
                        <span @class(['mf-shade' => $index % 2 === 1])>{{ $item?->kind }}</span>
                        <span @class(['justify-center', 'mf-shade' => $index % 2 === 1]) dir="ltr">{{ $item !== null ? $quantity($item->quantity) : '' }}</span>
                        <span @class(['mf-shade' => $index % 2 === 1])>{{ $item !== null ? $ar('conditions.'.$item->condition) : '' }}</span>
                    @endfor
                </div>
                <div class="mf-purchase-text">
                    <p>{{ $ar('pledges_title') }}</p>
                    <ul class="list-disc ps-10">
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
                        <span class="{{ $cell }} text-center" dir="ltr">{{ $quantity($item->quantity) }}</span>
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
                <table class="w-full border-collapse" data-test="print-invoices">
                    <thead>
                        <tr>
                            <th class="border border-secondary-light px-1 text-start">{{ $ar('merchant') }}</th>
                            <th class="border border-secondary-light px-1 text-start">{{ $ar('invoice_number') }}</th>
                            <th class="border border-secondary-light px-1 text-start">{{ $ar('materials') }}</th>
                            <th class="border border-secondary-light px-1 text-start">{{ $ar('amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="border border-secondary-light px-1">{{ $invoice->merchant }}</td>
                                <td class="border border-secondary-light px-1" dir="ltr">{{ $invoice->number }}</td>
                                <td class="border border-secondary-light px-1">{{ $invoice->description_ar }}</td>
                                <td class="border border-secondary-light px-1"><x-money :amount="$invoice->amount" :currency="$invoice->currency" /></td>
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
            <section class="mf-page">
                <img class="mf-bg" src="{{ $page('letterhead') }}" alt="">
                <div class="mf-body">
                    <h1 class="mb-4 text-center text-xl font-bold">{{ $ar('titles.protocol') }} <span dir="ltr">· {{ $en('titles.protocol') }}</span></h1>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                        <div>{{ $ar('beneficiary_name') }} <b>{{ $name }}</b> · <span dir="ltr">{{ $file->name_en }}</span></div>
                        <div>{{ $ar('national_number') }} <b dir="ltr">{{ $file->national_number }}</b></div>
                        <div>{{ $ar('project_number') }} <b dir="ltr">{{ $file->project_number }}</b></div>
                        <div>{{ $ar('donor') }} <b>{{ $funder }}</b></div>
                    </div>
                    <table class="mt-3 w-full border-collapse text-sm" data-test="print-invoices">
                        <thead>
                            <tr class="bg-primary text-white">
                                <th class="border border-primary px-2 py-1 text-start">#</th>
                                <th class="border border-primary px-2 py-1 text-start">{{ $ar('invoice_number') }}<br><span dir="ltr">{{ $en('invoice_number') }}</span></th>
                                <th class="border border-primary px-2 py-1 text-start">{{ $ar('date') }}<br><span dir="ltr">{{ $en('date') }}</span></th>
                                <th class="border border-primary px-2 py-1 text-start">{{ $ar('description') }}</th>
                                <th class="border border-primary px-2 py-1 text-start" dir="ltr">{{ $en('description') }}</th>
                                <th class="border border-primary px-2 py-1 text-start">{{ $ar('amount') }}<br><span dir="ltr">{{ $en('amount') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $index => $invoice)
                                <tr>
                                    <td class="border border-secondary-light px-2 py-1">{{ $index + 1 }}</td>
                                    <td class="border border-secondary-light px-2 py-1" dir="ltr">{{ $invoice->number }}</td>
                                    <td class="border border-secondary-light px-2 py-1" dir="ltr">{{ $day($invoice->purchase_date) }}</td>
                                    <td class="border border-secondary-light px-2 py-1">{{ $invoice->description_ar }}@if ($invoice->is_seizure) ({{ $ar('seizure_invoice') }})@endif</td>
                                    <td class="border border-secondary-light px-2 py-1" dir="ltr">{{ $invoice->description_en }}@if ($invoice->is_seizure) ({{ $en('seizure_invoice') }})@endif</td>
                                    <td class="border border-secondary-light px-2 py-1"><x-money :amount="$invoice->loan_amount" :currency="$file->currency" /></td>
                                </tr>
                            @endforeach
                            <tr class="font-semibold">
                                <td class="border border-secondary-light px-2 py-1" colspan="5">{{ $ar('total') }} / {{ $en('total') }}</td>
                                <td class="border border-secondary-light px-2 py-1"><x-money :amount="$totals['invoiced']" :currency="$file->currency" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-3 font-semibold">{{ $words['ar'] }}</p>
                    <p class="font-semibold" dir="ltr">{{ $words['en'] }}</p>
                    <p class="mt-3">{{ $ar('received_all') }}<br><span dir="ltr">{{ $en('received_all') }}</span></p>
                    @include('micro-files.signatures', ['roles' => [$ar('beneficiary').' / '.$en('beneficiary'), $ar('project_manager').' / '.$en('project_manager'), $ar('accounting').' / '.$en('accounting')]])
                </div>
            </section>
        @endif

        {{-- What a form has no room for, on a page of its own --}}
        @if ($rest->isNotEmpty())
            <section class="mf-page" data-test="print-annex">
                <img class="mf-bg" src="{{ $page('letterhead') }}" alt="">
                <div class="mf-body">
                    <h2 class="mb-3 text-center text-lg font-bold">{{ $ar('annex', ['form' => $ar('titles.'.$form)]) }}</h2>
                    <table class="w-full border-collapse text-sm">
                        <tbody>
                            @foreach ($rest as $index => $row)
                                <tr>
                                    <td class="border border-secondary-light px-2 py-1">{{ $rooms[$form] + $index + 1 }}</td>
                                    @if ($form === 'order')
                                        <td class="border border-secondary-light px-2 py-1"><x-money :amount="$row->amount" :currency="$row->currency" /></td>
                                        <td class="border border-secondary-light px-2 py-1" dir="ltr">{{ $row->number }}</td>
                                        <td class="border border-secondary-light px-2 py-1">{{ $row->description_ar }}</td>
                                        <td class="border border-secondary-light px-2 py-1">{{ $row->merchant }}</td>
                                    @else
                                        <td class="border border-secondary-light px-2 py-1">{{ $row->name }}</td>
                                        <td class="border border-secondary-light px-2 py-1">{{ $row->kind }}</td>
                                        <td class="border border-secondary-light px-2 py-1" dir="ltr">{{ $quantity($row->quantity) }}</td>
                                        <td class="border border-secondary-light px-2 py-1">{{ $ar('conditions.'.$row->condition) }}</td>
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
