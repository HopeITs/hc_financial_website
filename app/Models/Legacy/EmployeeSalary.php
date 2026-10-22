<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly salary of an assignment (`employee_salary`). Read only.
 */
class EmployeeSalary extends LegacyModel
{
    protected $table = 'employee_salary';

    protected $primaryKey = 'id';

    /**
     * @return BelongsTo<EmployeeSubProject, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EmployeeSubProject::class, 'employee_subproject_id');
    }
}
