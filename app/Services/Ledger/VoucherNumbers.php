<?php

namespace App\Services\Ledger;

use App\Enums\VoucherType;
use App\Models\VoucherSequence;
use App\Services\Ledger\Rules\NumbersAreNeverReused;
use Illuminate\Support\Facades\DB;

/**
 * Gives voucher numbers such as CRV-2026-0001 from `gl_voucher_sequences`. The sequence row is locked inside the
 * caller's transaction, so two postings never get the same number, and a posting that fails rolls its number back
 * with everything else. A number given to a voucher stays with it, even a rejected one.
 */
class VoucherNumbers
{
    public function __construct(private readonly NumbersAreNeverReused $neverReused) {}

    public function next(VoucherType $type, int $year): string
    {
        return DB::transaction(function () use ($type, $year) {
            VoucherSequence::query()->insertOrIgnore([
                'type' => $type->value, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $sequence = VoucherSequence::query()
                ->where('type', $type->value)->where('year', $year)->lockForUpdate()->firstOrFail();

            $next = $sequence->last_number + 1;
            VoucherSequence::query()->where('type', $type->value)->where('year', $year)->update(['last_number' => $next]);

            $number = sprintf('%s-%d-%04d', $type->value, $year, $next);
            $this->neverReused->check($number);

            return $number;
        });
    }
}
