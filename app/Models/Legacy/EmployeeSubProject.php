<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee assigned to a project and an expense line (`employee_subproject`). Read only.
 */
class EmployeeSubProject extends LegacyModel
{
    protected $table = 'employee_subproject';

    protected $primaryKey = 'id';

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<SubProject, $this>
     */
    public function subProject(): BelongsTo
    {
        return $this->belongsTo(SubProject::class, 'subproject_id');
    }

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'line_id');
    }

    /**
     * @return HasMany<EmployeeSalary, $this>
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'employee_subproject_id');
    }
}
