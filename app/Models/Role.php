<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A row of the Windows program's `role` table. Read only.
 */
class Role extends Model
{
    protected $table = 'role';

    public $timestamps = false;
}
