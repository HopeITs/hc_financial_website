<?php

namespace App\Support;

use App\Enums\ScreenAction;
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

    /** The entries the top bar's "New" menu offers, screen slug => icon, in the order they show (finance request of 2026-11-24). */
    public const NEW_ENTRIES = [
        'purchases' => 'shopping-cart',
        'requests' => 'clipboard-document-list',
        'advance' => 'hand-raised',
        'transfers' => 'arrows-right-left',
        'microfiles' => 'briefcase',
        'payments' => 'banknotes',
        'journal' => 'book-open',
    ];

    /**
     * The given entries the person may create, as links, in the given order: screen slug => icon in, slug, icon and
     * address out. Used by the home page's quick actions and the top bar's "New" menu.
     *
     * @param  array<string, string>  $entries
     * @return list<array{slug: string, icon: string, url: string}>
     */
    public static function creatable(?User $user, array $entries): array
    {
        if ($user === null) {
            return [];
        }

        $links = [];
        foreach ($entries as $slug => $icon) {
            if ($user->canOnScreen($slug, ScreenAction::Create)) {
                $links[] = ['slug' => $slug, 'icon' => $icon, 'url' => route(self::routeName($slug))];
            }
        }

        return $links;
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

    /** The menu group a screen is in, `entries` for `single`; null for a screen outside the menu. */
    public static function groupOf(?string $screen): ?string
    {
        foreach ((array) config('navigation.groups') as $group => $screens) {
            if (in_array($screen, $screens, true)) {
                return (string) $group;
            }
        }

        return null;
    }

    /**
     * Every screen of the menu by its address, with its group and label, for the page tabs to name a page they open
     * in the background (finance request of 2026-11-20). The home page is one of them.
     *
     * @return array<string, array{group: string, label: string}>
     */
    public static function tabScreens(): array
    {
        $screens = ['home' => ['group' => 'home', 'label' => __('home.title')]];

        foreach ((array) config('navigation.groups') as $group => $slugs) {
            foreach ($slugs as $slug) {
                $screens[self::path($slug)] = ['group' => (string) $group, 'label' => __('navigation.screens.'.$slug)];
            }
        }

        return $screens;
    }

    /** The route name of a screen: its path with dots, `reports.trial-balance` for `tb`. */
    public static function routeName(string $screen): string
    {
        return str_replace('/', '.', self::path($screen));
    }

    /**
     * The menu groups with the screens the person may view, leaving out the screens kept out of the menu
     * (config/navigation.php `hidden`).
     *
     * @return array<string, list<string>>
     */
    public static function visibleGroups(?User $user): array
    {
        $groups = [];
        $hidden = (array) config('navigation.hidden');

        foreach ((array) config('navigation.groups') as $group => $screens) {
            $visible = array_values(array_filter($screens, fn (string $screen): bool => ! in_array($screen, $hidden, true) && ($user?->canOnScreen($screen) ?? false)));

            if ($visible !== []) {
                $groups[$group] = $visible;
            }
        }

        return $groups;
    }
}
