<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's line of a monthly payroll, which is their payslip: amounts in the currency of their contract, `net_paid`
 * in the payroll's currency, and `allocations` how the paid amount splits over budget lines.
 *
 * @property int $id
 * @property int $payroll_id
 * @property int $employee_id
 * @property string $name
 * @property string|null $position
 * @property string $currency
 * @property string $basic_pay
 * @property string $adjustment
 * @property string $unpaid_days
 * @property string $unpaid_leave
 * @property string $other_deductions
 * @property string|null $notes
 * @property string $net
 * @property string $net_paid
 * @property list<array{budget_line: string, name: string, percent: string, amount: string}>|null $allocations
 */
class PayrollLine extends Model
{
    protected $table = 'gl_payroll_lines';

    protected $fillable = [
        'payroll_id', 'employee_id', 'name', 'position', 'currency', 'basic_pay', 'adjustment', 'unpaid_days', 'unpaid_leave',
        'other_deductions', 'notes', 'net', 'net_paid', 'allocations',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'basic_pay' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'unpaid_days' => 'decimal:2',
            'unpaid_leave' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net' => 'decimal:2',
            'net_paid' => 'decimal:2',
            'allocations' => 'array',
        ];
    }

    /** @return BelongsTo<Payroll, $this> */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
}
