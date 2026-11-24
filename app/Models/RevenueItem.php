<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A product or service a project sells, on its price list (finance request of 2026-11-24).
 *
 * @property int $id
 * @property string $project_code
 * @property string $name
 * @property string $kind one of self::KINDS
 * @property string $unit_price
 * @property string $currency
 * @property bool $active
 */
class RevenueItem extends Model
{
    /** The kinds of revenue, for the totals: sales of drinks and food, subscriptions, internet, paid courses, other. */
    public const KINDS = ['sales', 'subscription', 'internet', 'course', 'other'];

    protected $table = 'gl_revenue_items';

    protected $fillable = ['project_code', 'name', 'kind', 'unit_price', 'currency', 'active'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
