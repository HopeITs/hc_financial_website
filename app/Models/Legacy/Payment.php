<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An instalment due from a beneficiary (`payment`). Read only.
 */
class Payment extends LegacyModel
{
    protected $table = 'payment';

    protected $primaryKey = 'ID';

    /**
     * @return BelongsTo<Beneficiary, $this>
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class, 'Beneficiary_ID');
    }

    /**
     * @return HasMany<ActionPayment, $this>
     */
    public function actionPayments(): HasMany
    {
        return $this->hasMany(ActionPayment::class, 'payment_id');
    }
}
