<?php

namespace App\Support;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;

/**
 * The navigation groups a person may see: only the screens their roles can view, and no empty group.
 */
class Navigation
{
    /**
     * Counts to show beside a menu item, by screen slug: the vouchers waiting for approval, for anyone who can
     * open the vouchers screen. Screens with nothing to show are left out.
     *
     * @return array<string, int>
     */
    public static function badges(?User $user): array
    {
        if ($user === null || ! $user->canOnScreen('vouchers')) {
            return [];
        }

        $pending = Voucher::query()->where('status', VoucherStatus::PendingApproval->value)->count();

        return $pending > 0 ? ['vouchers' => $pending] : [];
    }

    /**
     * Every screen slug of the navigation.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_merge(...array_values((array) config('navigation.groups')));
    }

    /** The address of a screen, `reports/trial-balance` for `tb` (config/navigation.php `paths`). */
    public static function path(string $screen): string
    {
        return (string) config('navigation.paths.'.$screen);
    }

    /** The route name of a screen: its path with dots, `reports.trial-balance` for `tb`. */
    public static function routeName(string $screen): string
    {
        return str_replace('/', '.', self::path($screen));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function visibleGroups(?User $user): array
    {
        $groups = [];

        foreach ((array) config('navigation.groups') as $group => $screens) {
            $visible = array_values(array_filter($screens, fn (string $screen): bool => $user?->canOnScreen($screen) ?? false));

            if ($visible !== []) {
                $groups[$group] = $visible;
            }
        }

        return $groups;
    }
}
