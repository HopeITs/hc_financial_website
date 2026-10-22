<?php

namespace App\Models;

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Enums\NormalSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An account of the ledger's chart of accounts (header or posting).
 *
 * @property string $code
 * @property string|null $parent_code
 * @property AccountLevel $level
 * @property AccountClass $class
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $statement_line
 * @property NormalSide $normal_side
 * @property string|null $currency
 * @property string|null $city
 * @property bool $is_cash
 * @property list<string>|null $required_tags
 * @property string|null $usage_note
 * @property bool $is_active
 */
class GlAccount extends Model
{
    use SoftDeletes;

    protected $table = 'gl_accounts';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code', 'parent_code', 'level', 'class', 'name_en', 'name_ar', 'statement_line', 'normal_side',
        'currency', 'city', 'is_cash', 'required_tags', 'usage_note', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => AccountLevel::class,
            'class' => AccountClass::class,
            'normal_side' => NormalSide::class,
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
            'required_tags' => 'array',
        ];
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_code', 'code');
    }

    /** @return HasMany<GlAccount, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'code');
    }

    /** @return HasMany<GlBudgetLine, $this> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(GlBudgetLine::class, 'account_code', 'code');
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
