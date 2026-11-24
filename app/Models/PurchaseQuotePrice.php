<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A supplier's unit price for one item of a purchase file, and the brand or origin they offer.
 *
 * @property int $id
 * @property int $quote_id
 * @property int $item_id
 * @property string $unit_price
 * @property string|null $brand
 */
class PurchaseQuotePrice extends Model
{
    public $timestamps = false;

    protected $table = 'gl_purchase_quote_prices';

    protected $fillable = ['quote_id', 'item_id', 'unit_price', 'brand'];

    protected function casts(): array
    {
        return [
            'quote_id' => 'integer',
            'item_id' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }
}
