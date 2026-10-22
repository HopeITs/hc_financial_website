<?php

namespace App\Models;

use App\Enums\RoleId;
use App\Enums\ScreenAction;
use App\Models\Scopes\WithoutProfilePicture;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
#[Hidden(['Password', 'ProfilePicture'])]
#[ScopedBy(WithoutProfilePicture::class)]
class User extends Authenticatable
{
    protected $table = 'user';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    /**
     * The user's role (`role`.`name`, e.g. the financial manager).
     *
     * @return BelongsTo<Role, $this>
     */
    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'Role');
    }

    /**
     * Roles held only in the web app (the General Manager).
     *
     * @return HasMany<WebUserRole, $this>
     */
    public function webRoles(): HasMany
    {
        return $this->hasMany(WebUserRole::class, 'user_id', 'ID');
    }

    /**
     * Every role of the person: the legacy `role`, plus the web-only ones.
     *
     * @return list<RoleId>
     */
    public function roleIds(): array
    {
        $roles = [];

        $legacy = RoleId::tryFrom((int) ($this->attributes['Role'] ?? 0));
        if ($legacy !== null && ! $legacy->isWebOnly()) {
            $roles[] = $legacy;
        }

        foreach ($this->webRoles as $webRole) {
            $roles[] = $webRole->role_id;
        }

        return array_values(array_unique($roles, SORT_REGULAR));
    }

    /**
     * Whether any of the person's roles allows the action on the screen (config/permissions.php).
     */
    public function canOnScreen(string $slug, ScreenAction $action = ScreenAction::View): bool
    {
        foreach ($this->roleIds() as $role) {
            $actions = config("permissions.roles.{$role->value}.{$slug}", []);

            if (in_array($action->value, $actions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The test database's columns are PascalCase (`Username`, `Password`).
     *
     * @return Attribute<string, never>
     */
    protected function username(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->attributes['Username'] ?? ''));
    }

    public function getAuthPassword(): ?string
    {
        return $this->attributes['Password'] ?? null;
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
     * Only accounts with `State` = 1 may sign in (0 or empty is deactivated in the Windows program).
     */
    public function isActive(): bool
    {
        return (int) ($this->attributes['State'] ?? 0) === 1;
    }

    /**
     * The first letters of the username, for the avatar.
     */
    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->username, 0, 2));
    }

    /**
     * No remember-me: the `user` table has no column for the token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
