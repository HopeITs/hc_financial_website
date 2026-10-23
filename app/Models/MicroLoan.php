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

    protected $fillable = ['beneficiary', 'project_code', 'loan_amount', 'grant_amount', 'currency', 'legacy_micro_project_id'];

    protected function casts(): array
    {
        return [
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
