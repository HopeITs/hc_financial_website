<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An exchange rate period (`rate`). Read only.
 */
class Rate extends LegacyModel
{
    protected $table = 'rate';

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
    public function fromTillType(): BelongsTo
    {
        return $this->belongsTo(TillType::class, 'FromTill');
    }

    /**
     * @return BelongsTo<TillType, $this>
     */
    public function toTillType(): BelongsTo
    {
        return $this->belongsTo(TillType::class, 'ToTill');
    }

    /**
     * @return BelongsTo<Rate, $this>
     */
    public function observeRate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'ObserveRate_ID');
    }
}
