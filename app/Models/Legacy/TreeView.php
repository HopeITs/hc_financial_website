<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the Windows program's `tree_view` view: one leaf of the city, program, category,
 * project, account tree. A view has no key, so rows are only listed. Read only.
 *
 * @property int $CityID
 * @property string|null $CityName
 */
class TreeView extends LegacyModel
{
    protected $table = 'tree_view';

    protected $primaryKey = null;

    public $incrementing = false;

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'CityID');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'ProjectID');
    }

    /**
     * @return BelongsTo<SubProject, $this>
     */
    public function subProject(): BelongsTo
    {
        return $this->belongsTo(SubProject::class, 'SubprojectID');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'AccountID');
    }
}
