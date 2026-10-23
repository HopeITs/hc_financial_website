<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a request: an amount for one budget line, in the line's currency and in USD at the line rate.
 *
 * @property int $id
 * @property int $request_id
 * @property string $budget_line_key
 * @property string $amount
 * @property string $currency
 * @property string $rate
 * @property string $amount_usd
 * @property string|null $note
 */
class RequestLine extends Model
{
    protected $table = 'gl_request_lines';

    protected $fillable = ['request_id', 'budget_line_key', 'amount', 'currency', 'rate', 'amount_usd', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'rate' => 'decimal:6', 'amount_usd' => 'decimal:2'];
    }

    /** @return BelongsTo<GlBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(GlBudgetLine::class, 'budget_line_key', 'key');
    }
}
