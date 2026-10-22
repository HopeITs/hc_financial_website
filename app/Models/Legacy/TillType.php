<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A currency (`tilltypes`). Read only.
 */
class TillType extends LegacyModel
{
    protected $table = 'tilltypes';

    protected $primaryKey = 'ID';

    /**
     * @return HasMany<Till, $this>
     */
    public function tills(): HasMany
    {
        return $this->hasMany(Till::class, 'Type_ID');
    }
}
