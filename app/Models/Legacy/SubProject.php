<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A project of a program in one city (`subproject`). Read only.
 */
class SubProject extends LegacyModel
{
    protected $table = 'subproject';

    protected $primaryKey = 'ID';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'Project_ID');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'City_ID');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'SubProject_ID');
    }

    /**
     * @return BelongsToMany<Donor, $this>
     */
    public function donors(): BelongsToMany
    {
        return $this->belongsToMany(Donor::class, 'subproject_donor', 'SubProject_ID', 'Donor_ID');
    }
}
