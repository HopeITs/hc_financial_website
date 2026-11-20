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
 * @property string|null $disbursement_voucher_id
 * @property Carbon $created_at
 */
class MicroLoan extends Model
{
    protected $table = 'gl_micro_loans';

    protected $fillable = [
        'beneficiary', 'national_number', 'mobile', 'partners', 'project_code', 'loan_amount', 'grant_amount', 'currency',
        'payment_interval_months', 'first_payment_date', 'legacy_micro_project_id', 'disbursement_voucher_id',
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

    /** @return HasMany<MicroPayment, $this> the repayments and write-offs, oldest first */
    public function payments(): HasMany
    {
        return $this->hasMany(MicroPayment::class, 'loan_id')->orderBy('id');
    }

    /** @return BelongsTo<Voucher, $this> the payment voucher that gave the loan (none for loans opened before 2026-11-21) */
    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'disbursement_voucher_id')->withTrashed();
    }

    /**
     * What the beneficiary still owes: the instalments' amounts less what was paid and written off.
     *
     * @return numeric-string
     */
    public function owed(): string
    {
        return array_reduce($this->instalments->all(), fn (string $sum, MicroInstalment $instalment): string => bcadd($sum, $instalment->outstanding(), 2), '0.00');
    }
}
