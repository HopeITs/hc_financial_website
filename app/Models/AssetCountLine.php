<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One asset on a count: found or not (null while not checked yet), its condition and a note.
 *
 * @property int $id
 * @property int $count_id
 * @property int $asset_id
 * @property bool|null $found
 * @property string|null $condition
 * @property string|null $note
 */
class AssetCountLine extends Model
{
    public $timestamps = false;

    protected $table = 'gl_asset_count_lines';

    protected $fillable = ['count_id', 'asset_id', 'found', 'condition', 'note'];

    protected function casts(): array
    {
        return ['found' => 'boolean', 'asset_id' => 'integer'];
    }

    /** @return BelongsTo<FixedAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }
}
