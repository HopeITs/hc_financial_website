<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something that happened to a fixed asset (finance request of 2026-11-28): received, handed over to a person or
 * place, returned, moved between warehouses, sent for repair, counted, depreciated, written off or sold. Depreciation,
 * write-offs and sales carry the voucher they made.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $kind one of self::KINDS
 * @property Carbon $happened_on
 * @property string|null $month the month a depreciation is for, `2026-12`
 * @property int|null $from_warehouse_id
 * @property int|null $to_warehouse_id
 * @property string|null $from_custodian
 * @property string|null $to_custodian
 * @property string|null $condition
 * @property string|null $amount_usd
 * @property string|null $previous_status the asset's state before a write-off or a sale, to put back if its voucher is rejected
 * @property string|null $voucher_id
 * @property string|null $reference the paper's number (delivery note, report)
 * @property string|null $notes
 * @property int $created_by
 */
class AssetMovement extends Model
{
    public const KINDS = ['receive', 'handover', 'return', 'transfer', 'repair', 'count', 'depreciate', 'write_off', 'sell'];

    protected $table = 'gl_asset_movements';

    protected $fillable = [
        'asset_id', 'kind', 'happened_on', 'month', 'from_warehouse_id', 'to_warehouse_id', 'from_custodian', 'to_custodian',
        'condition', 'amount_usd', 'previous_status', 'voucher_id', 'reference', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'happened_on' => 'date',
            'amount_usd' => 'decimal:2',
            'from_warehouse_id' => 'integer',
            'to_warehouse_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<FixedAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }
}
