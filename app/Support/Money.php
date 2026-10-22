<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money as decimal strings with bcmath, never floats. Extra decimals are rounded half away from zero.
 */
final class Money
{
    /** @return numeric-string */
    public static function normalize(string|int|float|null $value, int $scale = 2): string
    {
        if ($value === null || $value === '') {
            return self::zero($scale);
        }

        $text = is_float($value) ? number_format($value, $scale + 4, '.', '') : (string) $value;

        if (! preg_match('/^-?\d+(\.\d+)?$/', $text) || ! is_numeric($text)) {
            throw new InvalidArgumentException("Not a plain decimal amount: {$text}");
        }

        $half = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);

        return str_starts_with($text, '-') ? bcsub($text, $half, $scale) : bcadd($text, $half, $scale);
    }

    /** @return numeric-string */
    public static function zero(int $scale = 2): string
    {
        return bcadd('0', '0', $scale);
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public static function abs(string $value, int $scale = 2): string
    {
        return bccomp($value, '0', $scale) < 0 ? bcmul($value, '-1', $scale) : $value;
    }
}
