<?php

use App\Support\FinancePassword;

it('verifies the format the Windows program writes', function () {
    // salt = bytes 0..15; hash = PBKDF2-HMAC-SHA256("secret", salt, 100000, 32), as PasswordHelper.cs does.
    $salt = implode('', array_map('chr', range(0, 15)));
    $stored = base64_encode($salt.hash_pbkdf2('sha256', 'secret', $salt, 100000, 32, true));

    expect(FinancePassword::verify('secret', $stored))->toBeTrue()
        ->and(FinancePassword::verify('Secret', $stored))->toBeFalse();
});

it('round trips through make', function () {
    $stored = FinancePassword::make('كلمة سر');

    expect(strlen((string) base64_decode($stored, true)))->toBe(48)
        ->and(FinancePassword::verify('كلمة سر', $stored))->toBeTrue()
        ->and(FinancePassword::verify('x', $stored))->toBeFalse();
});

it('rejects anything that is not a 48 byte base64 hash', function (?string $stored) {
    expect(FinancePassword::verify('secret', $stored))->toBeFalse();
})->with([null, '', 'not base64!!', base64_encode('too short')]);
