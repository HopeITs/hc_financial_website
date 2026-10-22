<?php

use App\Support\Money;

it('writes amounts as decimal strings with two decimals', function () {
    expect(Money::normalize('100'))->toBe('100.00')
        ->and(Money::normalize(100))->toBe('100.00')
        ->and(Money::normalize(12.5))->toBe('12.50')
        ->and(Money::normalize(null))->toBe('0.00')
        ->and(Money::normalize(''))->toBe('0.00')
        ->and(Money::normalize('1500000.5'))->toBe('1500000.50');
});

it('rounds half away from zero', function () {
    expect(Money::normalize('100.005'))->toBe('100.01')
        ->and(Money::normalize('100.004'))->toBe('100.00')
        ->and(Money::normalize('-1.005'))->toBe('-1.01')
        ->and(Money::normalize('-0.004'))->toBe('0.00');
});

it('keeps six decimals for rates', function () {
    expect(Money::normalize('15000', 6))->toBe('15000.000000')
        ->and(Money::normalize('1.1500005', 6))->toBe('1.150001');
});

it('refuses anything that is not a plain decimal amount', function (string $text) {
    expect(fn () => Money::normalize($text))->toThrow(InvalidArgumentException::class);
})->with(['abc', '1e3', '1,000.50', '--5', '5.']);

it('gives the absolute value', function () {
    expect(Money::abs('-0.03'))->toBe('0.03')
        ->and(Money::abs('0.03'))->toBe('0.03')
        ->and(Money::abs('0.00'))->toBe('0.00');
});
