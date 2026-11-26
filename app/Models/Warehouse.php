<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A store or place the organisation keeps fixed assets in (finance request of 2026-11-28).
 *
 * @property int $id
 * @property string $name
 * @property string|null $city
 * @property string|null $center
 * @property string|null $keeper who looks after it
 * @property bool $active
 */
class Warehouse extends Model
{
    protected $table = 'gl_warehouses';

    protected $fillable = ['name', 'city', 'center', 'keeper', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<FixedAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'warehouse_id');
    }
}
