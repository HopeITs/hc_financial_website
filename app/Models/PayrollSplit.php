<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One share of an employee's salary: a project and budget line with a percent, valid from the first day of a month.
 *
 * @property int $id
 * @property int $legacy_employee_id
 * @property string|null $employee_name
 * @property string $project_code
 * @property string $budget_line_key
 * @property string $percent
 * @property Carbon $valid_from
 */
class PayrollSplit extends Model
{
    protected $table = 'gl_payroll_splits';

    protected $fillable = ['legacy_employee_id', 'employee_name', 'project_code', 'budget_line_key', 'percent', 'valid_from'];

    protected function casts(): array
    {
        return [
            'legacy_employee_id' => 'integer',
            'percent' => 'decimal:2',
            'valid_from' => 'date',
        ];
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }

    /** @return BelongsTo<GlBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(GlBudgetLine::class, 'budget_line_key', 'key');
    }
}
