<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A micro-project beneficiary (`beneficiary`). Read only.
 */
class Beneficiary extends LegacyModel
{
    protected $table = 'beneficiary';

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
    public function tillType(): BelongsTo
    {
        return $this->belongsTo(TillType::class, 'currency_tilltype_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'Beneficiary_ID');
    }
}
