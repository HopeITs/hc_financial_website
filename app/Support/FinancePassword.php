<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The password hash the Windows program stores in `user`.`password`.
 *
 * Financial_WPF_App/Helper/PasswordHelper.cs writes base64(salt[16] + hash[32]),
 * the hash being PBKDF2-HMAC-SHA256 of the UTF-8 password with 100,000
 * iterations. The web app only reads that format, so the same account works in
 * both programs and changing a password in one changes it in the other.
 */
final class FinancePassword
{
    private const SaltLength = 16;

    private const HashLength = 32;

    private const Iterations = 100_000;

    public static function make(string $password, ?string $salt = null): string
    {
        $salt ??= random_bytes(self::SaltLength);

        return base64_encode($salt.self::derive($password, $salt));
    }

    public static function verify(string $password, ?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        $bytes = base64_decode($stored, true);

        if ($bytes === false || strlen($bytes) !== self::SaltLength + self::HashLength) {
            return false;
        }

        $salt = substr($bytes, 0, self::SaltLength);
        $hash = substr($bytes, self::SaltLength);

        return hash_equals($hash, self::derive($password, $salt));
    }

    private static function derive(string $password, string $salt): string
    {
        return hash_pbkdf2('sha256', $password, $salt, self::Iterations, self::HashLength, true);
    }
}
