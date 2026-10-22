<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ledger project: the unit that owns money. `legacy_account_id` points at the Windows program's `account.ID`.
 *
 * @property string $code
 * @property string $name
 * @property string|null $donor
 * @property string $restriction
 * @property string|null $city
 * @property string $donor_currency
 * @property string $donor_rate
 * @property int|null $legacy_account_id
 * @property bool $is_general_account
 */
class GlProject extends Model
{
    protected $table = 'gl_projects';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code', 'name', 'donor', 'restriction', 'city', 'donor_currency', 'donor_rate', 'legacy_account_id', 'is_general_account',
    ];

    protected function casts(): array
    {
        return [
            'donor_rate' => 'decimal:6',
            'legacy_account_id' => 'integer',
            'is_general_account' => 'boolean',
        ];
    }

    /** @return HasMany<GlBudgetLine, $this> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(GlBudgetLine::class, 'project_code', 'code');
    }
}
