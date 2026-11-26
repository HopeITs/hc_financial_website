<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One fixed asset of the register (finance request of 2026-11-28): what it is, what it cost, whose money bought it
 * (project and center), where it is and who holds it, and its state. Its depreciation so far is the sum of its
 * depreciation movements whose voucher still stands (a rejected voucher is deleted).
 *
 * @property int $id
 * @property string $tag
 * @property string $name
 * @property int $category_id
 * @property string $account_code
 * @property string $project_code
 * @property string|null $center
 * @property int|null $warehouse_id
 * @property string|null $custodian
 * @property string|null $serial
 * @property string|null $description
 * @property Carbon $acquired_on
 * @property string $cost
 * @property string $currency
 * @property string $rate
 * @property string $cost_usd
 * @property string $salvage_usd
 * @property int $life_months
 * @property string $depreciation_from the first month it is depreciated, `2026-12`
 * @property string $status one of self::STATUSES
 * @property string $condition one of self::CONDITIONS
 * @property string $source one of self::SOURCES
 * @property int|null $purchase_id
 * @property int|null $purchase_item_id
 * @property string|null $voucher_id the voucher that put it in the books, when one was needed
 * @property Carbon|null $ended_on
 * @property string|null $notes
 * @property int $created_by
 */
class FixedAsset extends Model
{
    public const IN_STORE = 'in_store';

    public const IN_USE = 'in_use';

    public const REPAIR = 'repair';

    public const WRITTEN_OFF = 'written_off';

    public const SOLD = 'sold';

    public const STATUSES = [self::IN_STORE, self::IN_USE, self::REPAIR, self::WRITTEN_OFF, self::SOLD];

    /** The states of an asset still owned, and so still depreciated and counted. */
    public const OWNED = [self::IN_STORE, self::IN_USE, self::REPAIR];

    public const CONDITIONS = ['new', 'good', 'fair', 'poor', 'broken'];

    /** Bought through a purchase file, given (in kind), or already owned when the register started. */
    public const SOURCES = ['purchase', 'donation', 'opening'];

    protected $table = 'gl_assets';

    protected $fillable = [
        'tag', 'name', 'category_id', 'account_code', 'project_code', 'center', 'warehouse_id', 'custodian', 'serial', 'description',
        'acquired_on', 'cost', 'currency', 'rate', 'cost_usd', 'salvage_usd', 'life_months', 'depreciation_from', 'status', 'condition',
        'source', 'purchase_id', 'purchase_item_id', 'voucher_id', 'ended_on', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'ended_on' => 'date',
            'cost' => 'decimal:2',
            'rate' => 'decimal:6',
            'cost_usd' => 'decimal:2',
            'salvage_usd' => 'decimal:2',
            'life_months' => 'integer',
            'warehouse_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function isOwned(): bool
    {
        return in_array($this->status, self::OWNED, true);
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /** @return HasMany<AssetMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class, 'asset_id')->orderByDesc('happened_on')->orderByDesc('id');
    }
}
