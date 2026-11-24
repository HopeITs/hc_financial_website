<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A supplier's quote for a purchase file, with a unit price for each item (gl_purchase_quote_prices) and its scan when
 * there is one.
 *
 * @property int $id
 * @property int $purchase_id
 * @property string $supplier
 * @property string|null $address
 * @property string|null $phone
 * @property Carbon|null $quoted_on
 * @property string|null $valid_for
 * @property string|null $original_name
 * @property string|null $stored_path
 * @property int $created_by
 */
class PurchaseQuote extends Model
{
    protected $table = 'gl_purchase_quotes';

    protected $fillable = ['purchase_id', 'supplier', 'address', 'phone', 'quoted_on', 'valid_for', 'original_name', 'stored_path', 'created_by'];

    protected function casts(): array
    {
        return [
            'quoted_on' => 'date',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    /** @return HasMany<PurchaseQuotePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PurchaseQuotePrice::class, 'quote_id');
    }
}
