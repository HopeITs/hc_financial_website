<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A budget line of one project, tied to the single expense account it posts to.
 *
 * @property string $key
 * @property string $project_code
 * @property string|null $donor
 * @property string $name
 * @property string $account_code
 * @property string $budget
 * @property string $currency
 * @property int|null $legacy_budget_line_id
 */
class GlBudgetLine extends Model
{
    protected $table = 'gl_budget_lines';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key', 'project_code', 'donor', 'name', 'account_code', 'budget', 'currency', 'legacy_budget_line_id',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'legacy_budget_line_id' => 'integer',
        ];
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_code', 'code');
    }
}
