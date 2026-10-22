<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physical cash box (`cashaccount`). Read only.
 */
class CashAccount extends LegacyModel
{
    protected $table = 'cashaccount';

    protected $primaryKey = 'id';

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * @return BelongsTo<TillType, $this>
     */
    public function tillType(): BelongsTo
    {
        return $this->belongsTo(TillType::class, 'currency_tilltype_id');
    }
}
