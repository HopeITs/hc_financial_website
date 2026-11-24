<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One project's payroll for a month (finance request of 2026-11-24), paid in `pay_currency`; a salary fixed in dollars
 * is paid in pounds at `rate`, which the Financial Manager sets.
 *
 * @property int $id
 * @property string $project_code
 * @property Carbon $month
 * @property string $pay_currency
 * @property string|null $rate
 * @property string $status self::DRAFT, SUBMITTED, APPROVED or PAID
 * @property list<array{role: int, user: int, name: string, at: string}>|null $approvals
 * @property Carbon|null $approved_at
 * @property string|null $return_reason
 * @property Carbon|null $paid_on
 * @property string|null $cash_account
 * @property string|null $voucher_id
 * @property int $prepared_by
 */
class Payroll extends Model
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    public const STATUSES = [self::DRAFT, self::SUBMITTED, self::APPROVED, self::PAID];

    protected $table = 'gl_payrolls';

    protected $fillable = [
        'project_code', 'month', 'pay_currency', 'rate', 'status', 'approvals', 'approved_at', 'return_reason', 'paid_on',
        'cash_account', 'voucher_id', 'prepared_by',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'rate' => 'decimal:6',
            'approvals' => 'array',
            'approved_at' => 'datetime',
            'paid_on' => 'date',
            'prepared_by' => 'integer',
        ];
    }

    /** @return HasMany<PayrollLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class, 'payroll_id')->orderBy('name');
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }
}
