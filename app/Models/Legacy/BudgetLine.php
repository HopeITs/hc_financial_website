<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a budget (`budget_line`). Read only.
 */
class BudgetLine extends LegacyModel
{
    protected $table = 'budget_line';

    protected $primaryKey = 'ID';

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'Budget_ID');
    }

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'Line_ID');
    }
}
