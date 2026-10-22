<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A program (`project`). Read only.
 */
class Project extends LegacyModel
{
    protected $table = 'project';

    protected $primaryKey = 'ID';

    /**
     * @return HasMany<SubProject, $this>
     */
    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class, 'Project_ID');
    }

    /**
     * @return BelongsToMany<Line, $this>
     */
    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'line_project', 'project_id', 'line_id');
    }
}
