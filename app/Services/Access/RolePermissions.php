<?php

namespace App\Services\Access;

use App\Enums\RoleId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;

/**
 * What each role allows (finance request of 2026-11-29): the list in config/permissions.php, unless the role was
 * changed on the users page; then the page's list (`web_role_permissions`) replaces it, until it is reset.
 * The Super User always allows everything and is not changed here.
 */
class RolePermissions
{
    /** The row that marks a role as changed on the page. */
    private const MARK = '*';

    /**
     * @return array<string, list<string>> screen slug => actions the role allows
     */
    public function of(RoleId $role): array
    {
        return self::custom()[$role->value] ?? $this->defaults($role);
    }

    public function allows(RoleId $role, string $slug, string $action): bool
    {
        return in_array($action, $this->of($role)[$slug] ?? [], true);
    }

    /**
     * @return array<string, list<string>> the role's list in config/permissions.php
     */
    public function defaults(RoleId $role): array
    {
        return (array) config("permissions.roles.{$role->value}", []);
    }

    public function isChanged(RoleId $role): bool
    {
        return array_key_exists($role->value, self::custom());
    }

    public function available(): bool
    {
        return self::tableExists();
    }

    /**
     * Gives a role exactly these `screen.action` permissions.
     *
     * @param  list<string>  $keys
     */
    public function replace(RoleId $role, array $keys, int $by): void
    {
        $now = now();
        $rows = [['role_id' => $role->value, 'screen' => self::MARK, 'action' => self::MARK, 'changed_by' => $by, 'created_at' => $now, 'updated_at' => $now]];

        foreach (array_unique($keys) as $key) {
            [$screen, $action] = explode('.', $key, 2) + [1 => ''];
            if ($screen !== '' && $action !== '') {
                $rows[] = ['role_id' => $role->value, 'screen' => $screen, 'action' => $action, 'changed_by' => $by, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::transaction(function () use ($role, $rows): void {
            DB::table('web_role_permissions')->where('role_id', $role->value)->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('web_role_permissions')->insert($chunk);
            }
        });

        Once::flush();
    }

    /** Gives a role back its list from config/permissions.php. */
    public function reset(RoleId $role): void
    {
        DB::table('web_role_permissions')->where('role_id', $role->value)->delete();
        Once::flush();
    }

    /**
     * The roles changed on the page, read once per request.
     *
     * @return array<int, array<string, list<string>>>
     */
    private static function custom(): array
    {
        return once(static function (): array {
            if (! self::tableExists()) {
                return [];
            }

            $roles = [];
            foreach (DB::table('web_role_permissions')->get(['role_id', 'screen', 'action']) as $row) {
                $roles[(int) $row->role_id] ??= [];
                if ($row->screen !== self::MARK) {
                    $roles[(int) $row->role_id][(string) $row->screen][] = (string) $row->action;
                }
            }

            return $roles;
        });
    }

    private static function tableExists(): bool
    {
        return once(static fn (): bool => Schema::hasTable('web_role_permissions'));
    }
}
