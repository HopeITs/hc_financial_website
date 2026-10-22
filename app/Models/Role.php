<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of the Windows program's `role` table. Read only.
 */
class Role extends Model
{
    protected $table = 'role';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->attributes['Name'] ?? ''));
    }
}
