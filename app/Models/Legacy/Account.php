<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A project account, the unit that owns money (`account`). Read only.
 */
class Account extends LegacyModel
{
    protected $table = 'account';

    protected $primaryKey = 'ID';

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * @return BelongsTo<SubProject, $this>
     */
    public function subProject(): BelongsTo
    {
        return $this->belongsTo(SubProject::class, 'SubProject_ID');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function observeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ObserveAccount_ID');
    }

    /**
     * @return HasMany<Till, $this>
     */
    public function tills(): HasMany
    {
        return $this->hasMany(Till::class, 'Account_ID');
    }

    /**
     * @return BelongsToMany<Donor, $this>
     */
    public function donors(): BelongsToMany
    {
        return $this->belongsToMany(Donor::class, 'account_donor', 'Account_id', 'Donor_id');
    }
}
