<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing a purchase file buys, on a budget line of its project, with its estimated price; when it comes, how much
 * came and in what state (goods), or when it was done and how well (a service, `condition`).
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $line_no
 * @property string $budget_line_key
 * @property string $description
 * @property string|null $unit
 * @property string $quantity
 * @property string $estimated_price
 * @property string|null $remarks
 * @property string|null $received_quantity
 * @property string|null $condition
 * @property Carbon|null $expected_on
 * @property Carbon|null $completed_on
 */
class PurchaseItem extends Model
{
    protected $table = 'gl_purchase_items';

    protected $fillable = [
        'purchase_id', 'line_no', 'budget_line_key', 'description', 'unit', 'quantity', 'estimated_price', 'remarks',
        'received_quantity', 'condition', 'expected_on', 'completed_on',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:2',
            'estimated_price' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'expected_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    /** @return BelongsTo<GlBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(GlBudgetLine::class, 'budget_line_key', 'key');
    }
}
