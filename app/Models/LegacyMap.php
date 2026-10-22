<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A confirmed mapping from a Windows program name (cash account, project, donor, budget line) to a ledger code.
 *
 * @property int $id
 * @property string $kind
 * @property string $legacy_key
 * @property string $target
 * @property int|null $confirmed_by
 */
class LegacyMap extends Model
{
    protected $table = 'gl_legacy_map';

    protected $fillable = ['kind', 'legacy_key', 'target', 'confirmed_by'];
}
