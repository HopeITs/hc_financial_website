<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The loan and the grant a micro project gives one beneficiary, with its repayment schedule.
 *
 * @property int $id
 * @property string $beneficiary
 * @property string|null $national_number
 * @property string|null $mobile
 * @property string|null $partners
 * @property int $payment_interval_months
 * @property Carbon|null $first_payment_date
 * @property string $project_code
 * @property string $loan_amount
 * @property string $grant_amount
 * @property string $currency
 * @property int|null $legacy_micro_project_id
 * @property Carbon $created_at
 */
class MicroLoan extends Model
{
    protected $table = 'gl_micro_loans';

    protected $fillable = [
        'beneficiary', 'national_number', 'mobile', 'partners', 'project_code', 'loan_amount', 'grant_amount', 'currency',
        'payment_interval_months', 'first_payment_date', 'legacy_micro_project_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_interval_months' => 'integer',
            'first_payment_date' => 'date',
            'loan_amount' => 'decimal:2',
            'grant_amount' => 'decimal:2',
            'legacy_micro_project_id' => 'integer',
        ];
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }

    /** @return HasMany<MicroInstalment, $this> */
    public function instalments(): HasMany
    {
        return $this->hasMany(MicroInstalment::class, 'loan_id')->orderBy('due_date')->orderBy('id');
    }
}
