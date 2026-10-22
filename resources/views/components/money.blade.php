@props(['amount'])

@php
    $value = (float) $amount;
    $class = $value < 0 ? 'fin-money fin-money-neg' : ($value == 0.0 ? 'fin-money fin-money-zero' : 'fin-money');
@endphp

{{-- Always left-to-right so the minus sign and digits stay in order inside Arabic pages. --}}
<span dir="ltr" {{ $attributes->class([$class]) }}>{{ number_format($value, 2) }}</span>
