<?php

namespace App\Services\Projects;

use App\Data\DataScope;
use App\Models\User;
use App\Services\Access\LegacyDataScope;

/**
 * Which projects a person may open and whether they see its money.
 * The limit is the Windows program's own data scope (see LegacyDataScope): every role except Head Office,
 * General Manager and Donor sees only the projects given to them.
 */
class ProjectAccess
{
    /**
     * The screen slug that stands for "may see project money" in config/permissions.php.
     */
    public const MONEY_SCREEN = 'project-money';

    public function __construct(private readonly LegacyDataScope $scope) {}

    /**
     * @return array<int, int>|null Account ids the person is limited to, or null when not limited.
     */
    public function limitedTo(User $user): ?array
    {
        return $this->scope->for($user)->projectLimit();
    }

    public function scopeOf(User $user): DataScope
    {
        return $this->scope->for($user);
    }

    public function canOpen(User $user, int $accountId): bool
    {
        $limit = $this->limitedTo($user);

        return $limit === null || in_array($accountId, $limit, true);
    }

    public function seesMoney(User $user): bool
    {
        return $user->canOnScreen(self::MONEY_SCREEN);
    }
}
