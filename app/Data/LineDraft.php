<?php

namespace App\Data;

use BackedEnum;

/**
 * One line of a voucher before it is posted. The attributes use the `gl_voucher_lines` column names;
 * money is a decimal string, never a float.
 */
final readonly class LineDraft
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    public function get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * @return numeric-string
     */
    public function amount(string $key): string
    {
        $value = $this->get($key);

        return is_numeric($value) ? bcadd((string) $value, '0', 2) : '0.00';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
