<?php

namespace App\Services\Projects;

use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which projects a person may open and whether they see its money.
 * A project manager (role 3) sees only the projects linked to them in `web_project_managers`;
 * anyone who also holds another role is not limited.
 */
class ProjectAccess
{
    /**
     * The screen slug that stands for "may see project money" in config/permissions.php.
     */
    public const MONEY_SCREEN = 'project-money';

    /**
     * @return array<int, int>|null Account ids the person is limited to, or null when not limited.
     */
    public function limitedTo(User $user): ?array
    {
        if ($user->roleIds() !== [RoleId::ProjectManager]) {
            return null;
        }

        return DB::table('web_project_managers')->where('user_id', $user->getKey())->pluck('account_id')->map(fn ($id): int => (int) $id)->values()->all();
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
