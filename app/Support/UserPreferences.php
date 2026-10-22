<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Small per-user settings kept in `web_user_preferences` (the app's own table, never a legacy one).
 */
class UserPreferences
{
    public const SIDEBAR_WIDTH = 'home.sidebar_width';

    public const MIN_SIDEBAR_WIDTH = 200;

    public const MAX_SIDEBAR_WIDTH = 480;

    public const DEFAULT_SIDEBAR_WIDTH = 280;

    public function get(int $userId, string $key): ?string
    {
        $value = DB::table('web_user_preferences')->where('user_id', $userId)->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    public function set(int $userId, string $key, string $value): void
    {
        DB::table('web_user_preferences')->upsert(
            [['user_id' => $userId, 'key' => $key, 'value' => $value, 'updated_at' => now()]],
            ['user_id', 'key'],
            ['value', 'updated_at'],
        );
    }

    public function sidebarWidth(int $userId): int
    {
        return $this->clampSidebarWidth((int) ($this->get($userId, self::SIDEBAR_WIDTH) ?? self::DEFAULT_SIDEBAR_WIDTH));
    }

    public function setSidebarWidth(int $userId, int $width): int
    {
        $width = $this->clampSidebarWidth($width);
        $this->set($userId, self::SIDEBAR_WIDTH, (string) $width);

        return $width;
    }

    public function clampSidebarWidth(int $width): int
    {
        return max(self::MIN_SIDEBAR_WIDTH, min(self::MAX_SIDEBAR_WIDTH, $width));
    }
}
