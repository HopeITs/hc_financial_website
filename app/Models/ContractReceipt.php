<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved receipt voucher (or part of it) linked to one instalment of an agreement: the amount in the agreement
 * currency and the dollars it was booked at.
 *
 * @property int $id
 * @property int $contract_id
 * @property int $instalment_id
 * @property string $voucher_id
 * @property string $amount
 * @property string $amount_usd
 * @property int $linked_by
 */
class ContractReceipt extends Model
{
    protected $table = 'gl_contract_receipts';

    protected $fillable = ['contract_id', 'instalment_id', 'voucher_id', 'amount', 'amount_usd', 'linked_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ContractInstalment, $this> */
    public function instalment(): BelongsTo
    {
        return $this->belongsTo(ContractInstalment::class, 'instalment_id');
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
