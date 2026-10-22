<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An expense line (`line`). Read only.
 */
class Line extends LegacyModel
{
    protected $table = 'line';

    protected $primaryKey = 'ID';

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'Line_ID');
    }
}
