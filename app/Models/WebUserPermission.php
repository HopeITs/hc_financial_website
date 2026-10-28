<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One action on one screen given to a single person, on top of their roles (`web_user_permissions`).
 *
 * @property int $user_id
 * @property string $screen
 * @property string $action
 */
class WebUserPermission extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
