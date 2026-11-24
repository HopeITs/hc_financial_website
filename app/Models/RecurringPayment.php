<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment that repeats (finance decision of 2026-11-24), such as rent: paid to `payee` every `every_months` months on
 * `next_due`, from `cash_account`, on a budget line of its project (RecurringPayments).
 *
 * @property int $id
 * @property string $project_code
 * @property string $budget_line_key
 * @property string $payee
 * @property string|null $description
 * @property string $currency
 * @property string $amount
 * @property int $every_months
 * @property Carbon $next_due
 * @property Carbon|null $ends_on
 * @property string $cash_account
 * @property string|null $center
 * @property int|null $request_id
 * @property bool $active
 * @property string|null $last_voucher_id
 * @property string|null $last_problem
 * @property int $created_by
 */
class RecurringPayment extends Model
{
    /** How often a payment can repeat, in months. */
    public const EVERY = [1, 2, 3, 6, 12];

    protected $table = 'gl_recurring_payments';

    protected $fillable = [
        'project_code', 'budget_line_key', 'payee', 'description', 'currency', 'amount', 'every_months', 'next_due', 'ends_on',
        'cash_account', 'center', 'request_id', 'active', 'last_voucher_id', 'last_problem', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'every_months' => 'integer',
            'next_due' => 'date',
            'ends_on' => 'date',
            'request_id' => 'integer',
            'active' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<GlBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(GlBudgetLine::class, 'budget_line_key', 'key');
    }
}
