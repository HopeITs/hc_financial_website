@php
    // The payroll summary and the payslips, on the letterhead, with the labels of the payslip template (lang
    // payroll_print, the same in both languages). The styles are public/css/purchase-forms.css, which needs no build.
    $t = fn (string $key, array $replace = []) => __('payroll_print.'.$key, $replace);
    $money = fn ($value): string => number_format((float) $value, 2);
    $period = $payroll->month->format('F Y');
    $paidOn = $payroll->paid_on?->format('d/m/Y') ?? '';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $t($form === 'summary' ? 'summary_title' : 'payslip_title') }} · {{ $payroll->project_code }} · {{ $payroll->month->format('m/Y') }}</title>
        @vite(['resources/css/app.css'])
        <link rel="stylesheet" href="{{ asset('css/purchase-forms.css') }}">
    </head>
    <body class="pf-sheet" data-test="payroll-print-{{ $form }}">
        <div class="pf-bar">
            <x-ui.button tone="primary" icon="printer" onclick="window.print()" data-test="print-now">{{ __('payroll.print_now') }}</x-ui.button>
        </div>

        @if ($form === 'summary')
            <section class="pf-page">
                <img class="pf-bg" src="{{ asset('images/micro-forms/letterhead.jpg') }}" alt="">
                <div class="pf-body">
                    <h1 class="pf-title">{{ $t('organisation') }} · {{ $t('summary_title') }}</h1>
                    <table class="pf-table">
                        <tr><td class="pf-label">{{ $t('month') }}</td><td>{{ $period }}</td><td class="pf-label">{{ $t('project') }}</td><td>{{ $payroll->project_code }} · {{ $payroll->project?->name }}</td></tr>
                        <tr><td class="pf-label">{{ $t('rate') }}</td><td dir="ltr">{{ $payroll->rate !== null ? rtrim(rtrim((string) $payroll->rate, '0'), '.') : '' }}</td><td class="pf-label">{{ $t('paid_in') }}</td><td>{{ $payroll->pay_currency }}</td></tr>
                    </table>
                    <table class="pf-table">
                        <thead>
                            <tr>
                                <th class="pf-small">{{ $t('employee_id') }}</th><th>{{ $t('employee_name') }}</th><th>{{ $t('position') }}</th>
                                <th class="pf-mid">{{ $t('basic_pay') }}</th><th class="pf-mid">{{ $t('adjustment') }}</th><th class="pf-mid">{{ $t('unpaid_leave') }}</th>
                                <th class="pf-mid">{{ $t('other_deductions') }}</th><th class="pf-mid">{{ $t('net_pay') }}</th><th class="pf-mid">{{ $t('net_paid', ['currency' => $payroll->pay_currency]) }}</th><th>{{ $t('notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payroll->lines as $line)
                                <tr>
                                    <td class="pf-center">{{ $line->employee_id }}</td><td>{{ $line->name }}</td><td>{{ $line->position }}</td>
                                    <td class="pf-num">{{ $money($line->basic_pay) }} {{ $line->currency }}</td><td class="pf-num">{{ $money($line->adjustment) }}</td>
                                    <td class="pf-num">{{ $money($line->unpaid_leave) }}</td><td class="pf-num">{{ $money($line->other_deductions) }}</td>
                                    <td class="pf-num pf-strong">{{ $money($line->net) }}</td><td class="pf-num pf-strong">{{ $money($line->net_paid) }}</td><td>{{ $line->notes }}</td>
                                </tr>
                            @endforeach
                            <tr><td colspan="8" class="pf-head">{{ $t('total') }}</td><td class="pf-num pf-strong">{{ $money($totals['paid']) }}</td><td></td></tr>
                        </tbody>
                    </table>
                    <table class="pf-table pf-signs">
                        <thead><tr><th>{{ $t('accountant') }}</th><th>{{ $t('hr') }}</th><th>{{ $t('finance_manager') }}</th></tr></thead>
                        <tbody><tr><td>{{ $preparer }}</td><td></td><td>{{ collect($payroll->approvals ?? [])->pluck('name')->implode(' / ') }}</td></tr></tbody>
                    </table>
                </div>
            </section>
        @else
            @foreach ($payroll->lines as $line)
                <section class="pf-page" data-test="payslip-{{ $line->employee_id }}">
                    <img class="pf-bg" src="{{ asset('images/micro-forms/letterhead.jpg') }}" alt="">
                    <div class="pf-body">
                        <h1 class="pf-title">{{ $t('payslip_title') }} · {{ $t('organisation') }} · {{ $t('country') }}</h1>
                        <table class="pf-table">
                            <tr><td class="pf-label">{{ $t('number') }}</td><td colspan="3">{{ $payroll->month->format('Ym') }}-{{ $line->employee_id }}</td></tr>
                            <tr><td class="pf-head" colspan="4">{{ $t('staff_information') }}</td></tr>
                            <tr><td class="pf-label">{{ $t('employee_id') }}</td><td>{{ $line->employee_id }}</td><td class="pf-label">{{ $t('position') }}</td><td>{{ $line->position }}</td></tr>
                            <tr><td class="pf-label">{{ $t('employee_name') }}</td><td>{{ $line->name }}</td><td class="pf-label">{{ $t('project') }}</td><td>{{ $payroll->project?->name ?? $payroll->project_code }}</td></tr>
                            <tr><td></td><td></td><td class="pf-label">{{ $t('pay_period') }}</td><td>{{ $period }} {{ $paidOn }}</td></tr>
                        </table>
                        <table class="pf-table">
                            <thead><tr><th>{{ $t('earnings') }}</th><th>{{ $t('amount') }} ({{ $line->currency }})</th><th>{{ $t('deductions') }}</th><th>{{ $t('amount') }} ({{ $line->currency }})</th></tr></thead>
                            <tbody>
                                <tr><td>{{ $t('basic_pay') }}</td><td class="pf-num">{{ $money($line->basic_pay) }}</td><td>{{ $t('unpaid_leave') }} ({{ rtrim(rtrim((string) $line->unpaid_days, '0'), '.') }} {{ $t('days') }})</td><td class="pf-num">{{ $money($line->unpaid_leave) }}</td></tr>
                                <tr><td>{{ $t('adjustment') }}</td><td class="pf-num">{{ $money($line->adjustment) }}</td><td>{{ $t('other_deductions') }}</td><td class="pf-num">{{ $money($line->other_deductions) }}</td></tr>
                                <tr><td class="pf-strong">{{ $t('total_earnings') }}</td><td class="pf-num pf-strong">{{ $money(bcadd((string) $line->basic_pay, (string) $line->adjustment, 2)) }}</td><td class="pf-strong">{{ $t('total_deductions') }}</td><td class="pf-num pf-strong">{{ $money(bcadd((string) $line->unpaid_leave, (string) $line->other_deductions, 2)) }}</td></tr>
                            </tbody>
                        </table>
                        <table class="pf-table">
                            <thead><tr><th colspan="3">{{ $t('allocations_note') }}</th></tr></thead>
                            <tbody>
                                @for ($i = 0; $i < 4; $i++)
                                    @php($share = ($line->allocations ?? [])[$i] ?? null)
                                    <tr><td>{{ $t('allocate', ['number' => $i + 1]) }} {{ $share['name'] ?? '' }}</td><td class="pf-num">{{ $share !== null ? rtrim(rtrim($share['percent'], '0'), '.').'%' : '' }}</td><td class="pf-num">{{ $share !== null ? $money($share['amount']) : '' }}</td></tr>
                                @endfor
                            </tbody>
                        </table>
                        <table class="pf-table">
                            <tr><td class="pf-label pf-wide">{{ $t('net_pay') }}</td><td class="pf-num pf-strong">{{ $money($line->net) }} {{ $line->currency }}</td></tr>
                            <tr><td class="pf-label pf-wide">{{ $t('net_paid', ['currency' => $payroll->pay_currency]) }}</td><td class="pf-num pf-strong">{{ $money($line->net_paid) }} {{ $payroll->pay_currency }}</td></tr>
                            <tr><td class="pf-label pf-wide">{{ $t('in_words') }}</td><td>{{ $words((string) $line->net_paid) }}</td></tr>
                            @if ($line->notes)
                                <tr><td class="pf-label pf-wide">{{ $t('notes') }}</td><td>{{ $line->notes }}</td></tr>
                            @endif
                        </table>
                        <p class="pf-text">{{ $t('confirm', ['accountant' => $preparer, 'period' => $period]) }}</p>
                        <table class="pf-table pf-signs">
                            <thead><tr><th>{{ $t('accountant') }}</th><th>{{ $t('hr') }}</th><th>{{ $t('staff_signature') }}</th></tr></thead>
                            <tbody><tr><td>{{ $preparer }}</td><td></td><td>{{ $line->name }}</td></tr></tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        @endif
    </body>
</html>
