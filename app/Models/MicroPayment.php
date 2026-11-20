<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A repayment or a write-off of a micro loan (finance decision of 2026-11-21): its voucher, the amount in the loan's
 * currency, what it took off the loan's book dollars (1204), and the instalments it settled, so that deleting or
 * rejecting the voucher gives them back.
 *
 * @property int $id
 * @property int $loan_id
 * @property string $voucher_id
 * @property string $kind self::RECEIPT or self::WRITE_OFF
 * @property string $amount
 * @property string $usd
 * @property list<array{instalment: int, amount: string}> $allocations
 * @property Carbon $created_at
 */
class MicroPayment extends Model
{
    public const RECEIPT = 'receipt';

    public const WRITE_OFF = 'writeoff';

    protected $table = 'gl_micro_payments';

    protected $fillable = ['loan_id', 'voucher_id', 'kind', 'amount', 'usd', 'allocations'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'usd' => 'decimal:2',
            'allocations' => 'array',
        ];
    }

    /** @return BelongsTo<MicroLoan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(MicroLoan::class, 'loan_id');
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id')->withTrashed();
    }
}
