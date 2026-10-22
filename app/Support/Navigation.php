<?php

namespace App\Support;

use App\Models\User;

/**
 * The navigation groups a person may see: only the screens their roles can view, and no empty group.
 */
class Navigation
{
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
