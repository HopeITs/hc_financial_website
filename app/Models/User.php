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
 * The table belongs to the Windows program: no remember-me token column, no
 * timestamps. It is written only from the Users screen (the password, role and
 * state of a person, adding and removing a person), and every change is logged.
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
            if ($webRole->role_id === RoleId::SuperUser && ! self::superUserAllowed()) {
                continue;
            }

            $roles[] = $webRole->role_id;
        }

        return array_values(array_unique($roles, SORT_REGULAR));
    }

    /** The Super User role works only when FINANCE_SUPER_USER is on and the app is not in production. */
    public static function superUserAllowed(): bool
    {
        return config('finance.super_user') === true && ! app()->environment('production');
    }

    /** Whether the person holds the Super User role and it is switched on: every screen, all data, every approval step. */
    public function isSuperUser(): bool
    {
        return in_array(RoleId::SuperUser, $this->roleIds(), true);
    }

    /**
     * Permissions given to this person alone, on top of their roles.
     *
     * @return HasMany<WebUserPermission, $this>
     */
    public function webPermissions(): HasMany
    {
        return $this->hasMany(WebUserPermission::class, 'user_id', 'ID');
    }

    /**
     * Whether a role of the person (config/permissions.php) or a permission given to them alone allows the action on the screen.
     */
    public function canOnScreen(string $slug, ScreenAction $action = ScreenAction::View): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        foreach ($this->roleIds() as $role) {
            $actions = config("permissions.roles.{$role->value}.{$slug}", []);

            if (in_array($action->value, $actions, true)) {
                return true;
            }
        }

        return $this->webPermissions->contains(fn (WebUserPermission $permission): bool => $permission->screen === $slug && $permission->action === $action->value);
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
