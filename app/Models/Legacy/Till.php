<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The money of one account in one currency (`till`). Read only.
 */
class Till extends LegacyModel
{
    protected $table = 'till';

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
        return $this->belongsTo(TillType::class, 'Type_ID');
    }
}
