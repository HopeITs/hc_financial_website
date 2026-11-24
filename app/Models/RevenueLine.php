<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a day's takings: what was sold, how many, at what price.
 *
 * @property int $id
 * @property int $sheet_id
 * @property int|null $item_id
 * @property string $kind
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 */
class RevenueLine extends Model
{
    public $timestamps = false;

    protected $table = 'gl_revenue_lines';

    protected $fillable = ['sheet_id', 'item_id', 'kind', 'description', 'quantity', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
