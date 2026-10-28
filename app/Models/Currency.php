<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A currency the books accept (R4-4). USD is the base; the others are quoted against it either as units per dollar
 * (`per_usd`) or as dollars per unit (`usd_per_unit`).
 *
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property string $quote
 * @property bool $is_base
 * @property bool $is_active
 * @property int $sort_order
 */
class Currency extends Model
{
    public const PER_USD = 'per_usd';

    public const USD_PER_UNIT = 'usd_per_unit';

    protected $table = 'gl_currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name_en', 'name_ar', 'quote', 'is_base', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_base' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
