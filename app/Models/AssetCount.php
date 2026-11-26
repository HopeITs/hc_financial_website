<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A count of the fixed assets of a warehouse, or of all of them (finance request of 2026-11-28): each asset found or
 * missing, and its condition. Closing it records a count movement on each asset.
 *
 * @property int $id
 * @property int|null $warehouse_id
 * @property Carbon $counted_on
 * @property string $status self::OPEN or self::CLOSED
 * @property string|null $counted_by_name
 * @property string|null $notes
 * @property int $created_by
 */
class AssetCount extends Model
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $table = 'gl_asset_counts';

    protected $fillable = ['warehouse_id', 'counted_on', 'status', 'counted_by_name', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['counted_on' => 'date', 'warehouse_id' => 'integer', 'created_by' => 'integer'];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /** @return HasMany<AssetCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(AssetCountLine::class, 'count_id')->orderBy('id');
    }
}
