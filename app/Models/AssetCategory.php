<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A kind of fixed asset (finance request of 2026-11-28): its fixed-asset account (1401 to 1408) and how many months it
 * is depreciated over.
 *
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string $account_code
 * @property int $life_months
 * @property bool $active
 */
class AssetCategory extends Model
{
    protected $table = 'gl_asset_categories';

    protected $fillable = ['name_en', 'name_ar', 'account_code', 'life_months', 'active'];

    protected function casts(): array
    {
        return ['life_months' => 'integer', 'active' => 'boolean'];
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
