<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A closed cash account merged into another (`into_account`) or written off (no `into_account`), with its voucher.
 *
 * @property int $id
 * @property string $from_account
 * @property string|null $into_account
 * @property string $voucher_id
 * @property int $confirmed_by
 */
class CashTillMerge extends Model
{
    protected $table = 'gl_cash_till_merges';

    protected $fillable = ['from_account', 'into_account', 'voucher_id', 'confirmed_by'];

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
