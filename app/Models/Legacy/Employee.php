<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee (`employee`). Read only.
 */
class Employee extends LegacyModel
{
    protected $table = 'employee';

    protected $primaryKey = 'id';

    /**
     * @return HasMany<EmployeeSubProject, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeSubProject::class, 'employee_id');
    }
}
