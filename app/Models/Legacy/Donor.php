<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A donor (`donor`). Read only.
 */
class Donor extends LegacyModel
{
    protected $table = 'donor';

    protected $primaryKey = 'ID';

    /**
     * @return BelongsToMany<Account, $this>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_donor', 'Donor_id', 'Account_id');
    }

    /**
     * @return BelongsToMany<SubProject, $this>
     */
    public function subProjects(): BelongsToMany
    {
        return $this->belongsToMany(SubProject::class, 'subproject_donor', 'Donor_ID', 'SubProject_ID');
    }
}
