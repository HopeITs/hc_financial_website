<?php

namespace App\Services\Ledger;

use App\Enums\VoucherType;
use App\Models\Voucher;
use App\Models\VoucherSequence;

/**
 * Rule 10: voucher numbers (CRV-2026-0001) are never reused. The counter of each type and year only goes up, under
 * a row lock so two people cannot get the same number, and a number that already exists on any voucher, even a
 * deleted one, is skipped. Call it inside the transaction that saves the voucher.
 */
class VoucherNumberAllocator
{
    public function next(VoucherType $type, int $year): string
    {
        VoucherSequence::query()->insertOrIgnore([[
            'type' => $type->value, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        $sequence = VoucherSequence::query()->where('type', $type->value)->where('year', $year)->lockForUpdate()->firstOrFail();
        $number = $sequence->last_number;

        do {
            $number++;
            $id = sprintf('%s-%d-%04d', $type->value, $year, $number);
        } while (Voucher::withTrashed()->whereKey($id)->exists());

        VoucherSequence::query()->where('type', $type->value)->where('year', $year)
            ->update(['last_number' => $number, 'updated_at' => now()]);

        return $id;
    }
}
