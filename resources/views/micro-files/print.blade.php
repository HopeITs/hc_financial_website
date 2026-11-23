@php
    // The forms print in Arabic whatever the screen's language; the receipt protocol adds the English lines.
    $ar = fn (string $key, array $replace = []) => __('micro_print.'.$key, $replace, 'ar');
    $en = fn (string $key, array $replace = []) => __('micro_print.'.$key, $replace, 'en');
    $name = $file->beneficiary;
    $funder = $file->project?->donor ?? $file->funder ?? '—';
    $quantity = fn ($value): string => rtrim(rtrim((string) $value, '0'), '.');
    $cell = 'border border-secondary-light px-2 py-1 text-start align-top';
    $head = 'border border-primary bg-primary px-2 py-1 text-start font-semibold text-white';
    $band = 'mt-4 mb-2 bg-primary py-1 text-center text-sm font-semibold text-white';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $ar('titles.'.$form) }} · {{ $name }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-white p-6 text-sm leading-relaxed text-secondary-dark print:p-0" data-test="micro-print-{{ $form }}">
        <div class="mb-4 print:hidden">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ $ar('print') }}</x-ui.button>
        </div>

        <header class="mb-4 flex items-center justify-between border-b-2 border-primary pb-2">
            <img src="{{ asset('logo.png') }}" alt="{{ $en('brand') }}" class="h-12">
            <b class="text-lg tracking-wide text-primary">{{ $ar('brand') }}</b>
        </header>

        <h1 class="mb-4 text-center text-2xl font-bold">
            {{ $ar('titles.'.$form) }}
            @if ($form === 'protocol')
                <span dir="ltr"> · {{ $en('titles.protocol') }}</span>
            @endif
        </h1>

        @if ($form === 'purchase')
            <p>{{ $ar('purchase_intro', ['number' => $file->project_number ?? '—', 'name' => $name, 'mobile' => $file->mobile ?? '—', 'profession' => $file->profession ?? '—', 'address' => $file->address ?? '—']) }}</p>
            <table class="mt-2 w-full border-collapse">
                <thead><tr><th class="{{ $head }}">{{ $ar('no') }}</th><th class="{{ $head }}">{{ $ar('item') }}</th><th class="{{ $head }}">{{ $ar('kind') }}</th><th class="{{ $head }}">{{ $ar('quantity') }}</th><th class="{{ $head }}">{{ $ar('condition') }}</th></tr></thead>
                <tbody>
                    @foreach ($file->items as $index => $item)
                        <tr><td class="{{ $cell }}">{{ $index + 1 }}</td><td class="{{ $cell }}">{{ $item->name }}</td><td class="{{ $cell }}">{{ $item->kind }}</td><td class="{{ $cell }}" dir="ltr">{{ $quantity($item->quantity) }}</td><td class="{{ $cell }}">{{ $ar('conditions.'.$item->condition) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-3">{{ $ar('pledges_title') }}</p>
            <ul class="list-disc ps-6">
                @foreach (__('micro_print.purchase_pledges', [], 'ar') as $pledge)
                    <li>{{ $pledge }}</li>
                @endforeach
            </ul>
            <p class="mt-3">{{ $ar('beneficiary_sign') }} ........................................</p>
            @include('micro-files.signatures', ['roles' => [$ar('micro_manager'), $ar('financial_manager'), $ar('purchasing_officer')]])
        @elseif ($form === 'quote')
            <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>{{ $ar('supplier') }} ........................................</div>
                <div>{{ $ar('filled_on') }} ....................</div>
                <div>{{ $ar('quote_for') }} <b>{{ $name }}</b></div>
            </div>
            <table class="mt-2 w-full border-collapse">
                <thead><tr><th class="{{ $head }}">{{ $ar('product') }}</th><th class="{{ $head }}">{{ $ar('kind') }}</th><th class="{{ $head }}">{{ $ar('needed') }}</th><th class="{{ $head }}">{{ $ar('price') }}</th><th class="{{ $head }}">{{ $ar('line_total') }}</th><th class="{{ $head }}">{{ $ar('notes') }}</th></tr></thead>
                <tbody>
                    @foreach ($file->items as $item)
                        <tr><td class="{{ $cell }}">{{ $item->name }}</td><td class="{{ $cell }}">{{ $item->kind }}</td><td class="{{ $cell }}" dir="ltr">{{ $quantity($item->quantity) }}</td><td class="{{ $cell }}"></td><td class="{{ $cell }}"></td><td class="{{ $cell }}"></td></tr>
                    @endforeach
                    <tr><td class="{{ $cell }}" colspan="3"></td><td class="{{ $cell }} font-semibold">{{ $ar('final_total') }}</td><td class="{{ $cell }}"></td><td class="{{ $cell }}"></td></tr>
                </tbody>
            </table>
            <p class="mt-3 text-xs">{{ $ar('quote_note') }}</p>
            @include('micro-files.signatures', ['roles' => [$ar('merchant_sign'), '', $ar('stamp')]])
        @elseif ($form === 'order')
            <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>{{ $ar('order_date') }} <b dir="ltr">{{ ($file->submitted_at ?? $file->created_at)?->format('d/m/Y') }}</b></div>
                <div>{{ $ar('order_number') }} <b dir="ltr">{{ $file->id }}</b></div>
            </div>
            <p class="{{ $band }}">{{ $ar('project_info') }}</p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>{{ $ar('beneficiary_name') }} <b>{{ $name }}</b></div>
                <div>{{ $ar('beneficiary_en') }} <b dir="ltr">{{ $file->name_en ?? '—' }}</b></div>
                <div>{{ $ar('project') }} <b>{{ $file->profession ?? '—' }}</b></div>
                <div>{{ $ar('project_number') }} <b dir="ltr">{{ $file->project_number ?? '—' }}</b></div>
                <div>{{ $ar('mobile') }} <b dir="ltr">{{ $file->mobile ?? '—' }}</b></div>
                <div>{{ $ar('national_number') }} <b dir="ltr">{{ $file->national_number ?? '—' }}</b></div>
                <div>{{ $ar('donor') }} <b>{{ $funder }}</b></div>
                <div>{{ $ar('first_due') }} <b>{{ $file->first_payment_date?->format('d/m/Y') ?? $ar('first_due_later') }}</b></div>
            </div>
            <p class="{{ $band }}">{{ $ar('budget') }}</p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>{{ $ar('requested') }} <x-money :amount="$file->requested_amount" :currency="$file->currency" /></div>
                <div>{{ $ar('seizure') }} <x-money :amount="$file->seizure_fee" :currency="$file->currency" /></div>
                <div>{{ $ar('percent') }} <b dir="ltr">{{ $quantity($file->recovery_percent) }}%</b></div>
                <div>{{ $ar('instalment') }} <x-money :amount="$file->instalment_amount" :currency="$file->currency" /></div>
                @if ($file->currency === 'SYP')
                    <div>{{ $ar('usd_equivalent') }} <x-money :amount="$usdValue" currency="USD" /> <span dir="ltr">@ {{ $quantity($file->rate) }}</span></div>
                @endif
            </div>
            <p class="mt-2 text-xs">{{ $ar('extra_note_up') }}<br>{{ $ar('extra_note_over') }}</p>
            @if (($file->approvals ?? []) !== [])
                <p class="mt-2">{{ $ar('approvals') }}
                    @foreach ($file->approvals as $step)
                        {{ \App\Enums\RoleId::tryFrom((int) $step['role'])?->label() }}: {{ $names[(int) $step['user']] ?? $step['name'] }} ({{ $step['at'] }})@if (! $loop->last) · @endif
                    @endforeach
                </p>
            @endif
            @include('micro-files.signatures', ['roles' => [$ar('project_manager'), '', $ar('finance_officer')]])
            <p class="{{ $band }}">{{ $ar('extra') }}</p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>{{ $ar('extra_amount') }} <x-money :amount="$file->extra_amount" :currency="$file->currency" /></div>
                <div>{{ $ar('extra_reason') }} {{ $file->extra_reason ?? '—' }}</div>
            </div>
            <p class="{{ $band }}">{{ $ar('invoices') }}</p>
            <table class="w-full border-collapse">
                <thead><tr><th class="{{ $head }}">{{ $ar('invoice_amount') }}</th><th class="{{ $head }}">{{ $ar('invoice_number') }}</th><th class="{{ $head }}">{{ $ar('statement') }}</th><th class="{{ $head }}">{{ $ar('notes') }}</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr><td class="{{ $cell }}"><x-money :amount="$invoice->amount" :currency="$invoice->currency" /></td><td class="{{ $cell }}" dir="ltr">{{ $invoice->number }}</td><td class="{{ $cell }}">{{ $invoice->description_ar }}</td><td class="{{ $cell }}">{{ $invoice->merchant }}@if ($invoice->is_seizure) · {{ $ar('seizure_invoice') }}@endif</td></tr>
                    @empty
                        <tr><td class="{{ $cell }} h-8" colspan="4"></td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-2">{{ $ar('invoices_total') }} <x-money :amount="$totals['invoiced']" :currency="$file->currency" /></p>
            <p class="mt-1 text-xs">{{ $ar('under_note') }}</p>
            @include('micro-files.signatures', ['roles' => [$ar('project_manager'), $ar('purchasing_officer'), $ar('finance_officer')]])
        @elseif ($form === 'bond')
            <p>{{ $ar('bond_signer', ['name' => $name, 'number' => $file->national_number ?? '....................']) }}</p>
            <p class="mt-2">{{ $ar('bond_received', ['amount' => '']) }} <x-money :amount="$total" :currency="$file->currency" /></p>
            <p class="font-semibold">{{ $words['ar'] }}</p>
            <p class="mt-2">{{ $ar('bond_text') }}</p>
            <p class="mt-2">{{ $ar('bond_address') }}</p>
            <p class="mt-2">{{ $ar('place_date') }}</p>
            @include('micro-files.signatures', ['roles' => [$ar('declarant'), $ar('witness'), $ar('witness')]])
        @elseif ($form === 'protocol')
            <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>{{ $ar('beneficiary_name') }} <b>{{ $name }}</b> · <span dir="ltr">{{ $file->name_en }}</span></div>
                <div>{{ $ar('national_number') }} <b dir="ltr">{{ $file->national_number ?? '—' }}</b></div>
                <div>{{ $ar('project_number') }} <b dir="ltr">{{ $file->project_number ?? '—' }}</b></div>
                <div>{{ $ar('donor') }} <b>{{ $funder }}</b></div>
            </div>
            <table class="mt-2 w-full border-collapse">
                <thead>
                    <tr>
                        <th class="{{ $head }}">#</th>
                        <th class="{{ $head }}">{{ $ar('invoice_number') }}<br><span dir="ltr">{{ $en('invoice_number') }}</span></th>
                        <th class="{{ $head }}">{{ $ar('date') }}<br><span dir="ltr">{{ $en('date') }}</span></th>
                        <th class="{{ $head }}">{{ $ar('description') }}</th>
                        <th class="{{ $head }}" dir="ltr">{{ $en('description') }}</th>
                        <th class="{{ $head }}">{{ $ar('amount') }}<br><span dir="ltr">{{ $en('amount') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $index => $invoice)
                        <tr>
                            <td class="{{ $cell }}">{{ $index + 1 }}</td>
                            <td class="{{ $cell }}" dir="ltr">{{ $invoice->number }}</td>
                            <td class="{{ $cell }}" dir="ltr">{{ $invoice->purchase_date->format('d/m/Y') }}</td>
                            <td class="{{ $cell }}">{{ $invoice->description_ar }}@if ($invoice->is_seizure) ({{ $ar('seizure_invoice') }})@endif</td>
                            <td class="{{ $cell }}" dir="ltr">{{ $invoice->description_en }}@if ($invoice->is_seizure) ({{ $en('seizure_invoice') }})@endif</td>
                            <td class="{{ $cell }}"><x-money :amount="$invoice->loan_amount" :currency="$file->currency" /></td>
                        </tr>
                    @endforeach
                    <tr><td class="{{ $cell }} font-semibold" colspan="5">{{ $ar('total') }} / {{ $en('total') }}</td><td class="{{ $cell }} font-semibold"><x-money :amount="$totals['invoiced']" :currency="$file->currency" /></td></tr>
                </tbody>
            </table>
            <p class="mt-2 font-semibold">{{ $words['ar'] }}</p>
            <p class="font-semibold" dir="ltr">{{ $words['en'] }}</p>
            <p class="mt-2">{{ $ar('received_all') }}<br><span dir="ltr">{{ $en('received_all') }}</span></p>
            @include('micro-files.signatures', ['roles' => [$ar('beneficiary').' / '.$en('beneficiary'), $ar('project_manager').' / '.$en('project_manager'), $ar('accounting').' / '.$en('accounting')]])
        @elseif ($form === 'undertaking')
            <table class="w-full border-collapse">
                <thead><tr><th class="{{ $head }}">{{ $ar('merchant') }}</th><th class="{{ $head }}">{{ $ar('invoice_number') }}</th><th class="{{ $head }}">{{ $ar('date') }}</th><th class="{{ $head }}">{{ $ar('materials') }}</th><th class="{{ $head }}">{{ $ar('amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr><td class="{{ $cell }}">{{ $invoice->merchant }}</td><td class="{{ $cell }}" dir="ltr">{{ $invoice->number }}</td><td class="{{ $cell }}" dir="ltr">{{ $invoice->purchase_date->format('d/m/Y') }}</td><td class="{{ $cell }}">{{ $invoice->description_ar }}</td><td class="{{ $cell }}"><x-money :amount="$invoice->amount" :currency="$invoice->currency" /></td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-3">{{ $ar('undertaking_text') }}</p>
            <p class="mt-2 font-semibold">{{ $ar('undertaking_pledges_title') }}</p>
            <ul class="list-disc ps-6">
                @foreach (__('micro_print.undertaking_pledges', [], 'ar') as $pledge)
                    <li>{{ $pledge }}</li>
                @endforeach
            </ul>
            <p class="mt-3">{{ $ar('undertaking_sign', ['name' => $name]) }}</p>
            @include('micro-files.signatures', ['roles' => [$ar('micro_manager_sign'), '', $ar('purchasing_sign')]])
        @elseif ($form === 'card')
            <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>{{ $ar('beneficiary_name') }} <b>{{ $name }}</b></div>
                <div>{{ $ar('project_number') }} <b dir="ltr">{{ $file->project_number ?? '—' }}</b></div>
                <div>{{ $ar('supporter') }} <b>{{ $funder }}</b></div>
                <div>{{ $ar('recovery') }} <x-money :amount="$file->loan_amount" :currency="$file->currency" /></div>
            </div>
            <table class="mt-2 w-full border-collapse">
                <thead><tr><th class="{{ $head }}">#</th><th class="{{ $head }}">{{ $ar('due_date') }}</th><th class="{{ $head }}">{{ $ar('instalment') }}</th><th class="{{ $head }}">{{ $ar('paid_on') }}</th><th class="{{ $head }}">{{ $ar('receipt_number') }}</th><th class="{{ $head }}">{{ $ar('signature') }}</th></tr></thead>
                <tbody>
                    @foreach ($file->instalments as $index => $instalment)
                        <tr><td class="{{ $cell }}">{{ $index + 1 }}</td><td class="{{ $cell }}" dir="ltr">{{ $instalment->due_date->format('d/m/Y') }}</td><td class="{{ $cell }}"><x-money :amount="$instalment->amount" /></td><td class="{{ $cell }}"></td><td class="{{ $cell }}"></td><td class="{{ $cell }}"></td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-3 border border-secondary-light p-3 text-xs">{{ $ar('card_hours') }}</p>
        @endif

        <footer class="mt-8 text-center text-xs text-primary">{{ $ar('footer') }}</footer>
    </body>
</html>
