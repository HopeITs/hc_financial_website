<?php

namespace App\Models;

use App\Enums\RoleId;
use Illuminate\Database\Eloquent\Model;

/**
 * A role a person holds only in the web app (`web_user_roles`), e.g. the General Manager.
 *
 * @property int $user_id
 * @property RoleId $role_id
 */
class WebUserRole extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['role_id' => RoleId::class];
    }
}
