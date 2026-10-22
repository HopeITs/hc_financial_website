<?php

namespace App\Models\Legacy;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The approval limit of a role in one currency (`role_currency_limit`). Read only.
 *
 * The table's key is (Role_ID, TillType_ID), which Eloquent cannot address as a primary key,
 * so rows are read through queries and relations, never found by key.
 */
class RoleCurrencyLimit extends LegacyModel
{
    protected $table = 'role_currency_limit';

    protected $primaryKey = null;

    public $incrementing = false;

    /**
     * The role lives on the default connection (it is the login table).
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'Role_ID');
    }

    /**
     * @return BelongsTo<TillType, $this>
     */
    public function tillType(): BelongsTo
    {
        return $this->belongsTo(TillType::class, 'TillType_ID');
    }
}
