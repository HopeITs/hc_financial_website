<?php

namespace App\Services\Projects;

use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which projects a person may open and whether they see their money.
 *
 * The projects given to a person are kept by the web program (`web_user_projects`, set on the users screen); they came
 * from the Windows program's links until it was left (finance decision of 2026-11-27). Head Office, the General Manager,
 * the Donor and the Super User see everything, and so does anyone with a role that is not limited at all
 * (config finance.scoped_roles, the project manager by default). The others see only their own projects.
 */
class ProjectAccess
{
    /**
     * The screen slug that stands for "may see project money" in config/permissions.php.
     */
    public const MONEY_SCREEN = 'project-money';

    private const UNLIMITED = [RoleId::HeadOffice, RoleId::GeneralManager, RoleId::Donor, RoleId::SuperUser];

    /** @var array<int, list<string>|null> the answer of each person, for one request */
    private array $known = [];

    /**
     * @return list<string>|null the codes of the projects the person is limited to, or null when not limited
     */
    public function limitedTo(User $user): ?array
    {
        $id = (int) $user->getKey();

        if (! array_key_exists($id, $this->known)) {
            // Someone with no role at all sees no project.
            $this->known[$id] = $user->roleIds() === [] ? [] : ($this->isLimited($user) ? $this->given($id) : null);
        }

        return $this->known[$id];
    }

    public function canOpen(User $user, ?string $projectCode): bool
    {
        $limit = $this->limitedTo($user);

        return $limit === null || ($projectCode !== null && in_array($projectCode, $limit, true));
    }

    public function seesMoney(User $user): bool
    {
        return $user->canOnScreen(self::MONEY_SCREEN);
    }

    /**
     * The projects given to a person, whatever their role.
     *
     * @return list<string>
     */
    public function given(int $userId): array
    {
        return array_values(array_map('strval', DB::table('web_user_projects')->where('user_id', $userId)->orderBy('project_code')->pluck('project_code')->all()));
    }

    /**
     * Gives a person exactly these projects.
     *
     * @param  list<string>  $projectCodes
     */
    public function give(int $userId, array $projectCodes): void
    {
        $codes = array_values(array_unique(array_filter(array_map('strval', $projectCodes), fn (string $code): bool => $code !== '')));
        $now = now();

        DB::transaction(function () use ($userId, $codes, $now): void {
            DB::table('web_user_projects')->where('user_id', $userId)->whereNotIn('project_code', $codes)->delete();
            DB::table('web_user_projects')->insertOrIgnore(array_map(fn (string $code): array => [
                'user_id' => $userId, 'project_code' => $code, 'created_at' => $now, 'updated_at' => $now,
            ], $codes));
        });

        unset($this->known[$userId]);
    }

    private function isLimited(User $user): bool
    {
        return $this->limits($user->roleIds());
    }

    /**
     * Whether a person holding these roles sees only the projects given to them.
     *
     * @param  list<RoleId>  $roles
     */
    public function limits(array $roles): bool
    {
        if ($roles === []) {
            return true;
        }

        $scoped = (array) config('finance.scoped_roles', []);

        foreach ($roles as $role) {
            if (in_array($role, self::UNLIMITED, true) || ! in_array($role->name, $scoped, true)) {
                return false;
            }
        }

        return true;
    }
}
