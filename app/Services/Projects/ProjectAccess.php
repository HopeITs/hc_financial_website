<?php

namespace App\Services\Projects;

use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which projects a person may open and whether they see their money.
 *
 * The projects given to a person are kept by the web program (`web_user_projects`, set on the users screen); they came
 * from the Windows program's links until it was left (finance decision of 2026-11-27). Head Office, the General Manager,
 * the Donor and the Super User see everything, and so does anyone with a role that is not limited at all
 * (config finance.scoped_roles, the project manager by default). The others see only their own projects.
 * Anyone else is limited too once switched on for it on the users page (finance request of 2026-11-30): they then see
 * and enter only their projects, on every screen and in every voucher (rule 17, ProjectsOfThePreparer).
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
            $this->known[$id] = $user->roleIds() === [] ? [] : ($this->isLimited($user) || $this->switchedOn($user) ? $this->given($id) : null);
        }

        return $this->known[$id];
    }

    /**
     * Whether the person was limited to their projects on the users page (finance request of 2026-11-30), whatever their
     * role; Head Office, the General Manager, the Donor and the Super User are never limited.
     */
    public function switchedOn(User $user): bool
    {
        foreach ($user->roleIds() as $role) {
            if (in_array($role, self::UNLIMITED, true)) {
                return false;
            }
        }

        return $this->isSwitchedOn((int) $user->getKey());
    }

    /**
     * Whether a person holding these roles sees every project whatever is switched on for them.
     *
     * @param  list<RoleId>  $roles
     */
    public function neverLimited(array $roles): bool
    {
        return array_intersect(array_map(fn (RoleId $role): int => $role->value, $roles), array_map(fn (RoleId $role): int => $role->value, self::UNLIMITED)) !== [];
    }

    /** Whether the switch is on for this person, whatever their roles. */
    public function isSwitchedOn(int $userId): bool
    {
        return self::switchAvailable() && (bool) DB::table('web_user_profiles')->where('user_id', $userId)->value('limit_projects');
    }

    /** Turns the switch on or off for a person. */
    public function switchTo(int $userId, bool $on): void
    {
        if (! self::switchAvailable()) {
            return;
        }

        $now = now();
        DB::table('web_user_profiles')->updateOrInsert(['user_id' => $userId], ['limit_projects' => $on, 'updated_at' => $now]);
        unset($this->known[$userId]);
    }

    /** Whether the switch's column is there yet (IT runs its migration after the deploy). */
    public static function switchAvailable(): bool
    {
        return once(static fn (): bool => Schema::hasTable('web_user_profiles') && Schema::hasColumn('web_user_profiles', 'limit_projects'));
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
