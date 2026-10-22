<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A city (`city`). Read only.
 */
class City extends LegacyModel
{
    protected $table = 'city';

    protected $primaryKey = 'ID';

    /**
     * @return HasMany<SubProject, $this>
     */
    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class, 'City_ID');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'city_id');
    }

    /**
     * @return HasMany<CashAccount, $this>
     */
    public function cashAccounts(): HasMany
    {
        return $this->hasMany(CashAccount::class, 'city_id');
    }
}
