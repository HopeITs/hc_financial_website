<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A budget of an account (`budget`). Read only.
 */
class Budget extends LegacyModel
{
    protected $table = 'budget';

    protected $primaryKey = 'ID';

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'Account_ID');
    }

    /**
     * @return BelongsTo<TillType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TillType::class, 'Budget_Type_ID');
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'Budget_ID');
    }
}
