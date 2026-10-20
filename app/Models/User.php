<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A row of the Windows program's `user` table (id, username, password, role).
 *
 * The table belongs to the Windows program, so this model never writes to it:
 * no remember-me token column, no timestamps, and the password is only read.
 *
 * @property int $id
 * @property string $username
 * @property string $password
 */
#[Hidden(['password'])]
class User extends Authenticatable
{
    protected $table = 'user';

    public $timestamps = false;

    /**
     * The user's role (`role`.`name`, e.g. the financial manager).
     *
     * @return BelongsTo<Role, $this>
     */
    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role');
    }

    /**
     * What the interface shows as the person's name.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => (string) $this->username);
    }

    /**
     * The first letters of the username, for the avatar.
     */
    public function initials(): string
    {
        return mb_strtoupper(mb_substr((string) $this->username, 0, 2));
    }

    /**
     * No remember-me: the `user` table has no column for the token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
