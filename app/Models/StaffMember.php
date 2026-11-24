<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An employee on the staff register (`gl_staff`): the salary of their contract and the project whose payroll they are
 * on. `employee_id` is the same id as their payroll split (gl_payroll_splits.legacy_employee_id).
 *
 * @property int $id
 * @property int $employee_id
 * @property string $name
 * @property string|null $position
 * @property string $project_code
 * @property string $basic_pay
 * @property string $currency
 * @property bool $active
 */
class StaffMember extends Model
{
    protected $table = 'gl_staff';

    protected $fillable = ['employee_id', 'name', 'position', 'project_code', 'basic_pay', 'currency', 'active'];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'basic_pay' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
