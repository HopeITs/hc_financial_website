<?php

namespace App\Services\Access;

use App\Data\DataScope;
use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * The Windows program's data scope (`City::Select_Tree_View`), read from its own link tables:
 * `user_accounts` (sub-project rows have no account; the others name an account) and `user_cashaccounts`.
 *
 * By role:
 *  - Head Office, General Manager, Donor: everything (the program applies no limit to them).
 *  - Project Manager, Financial Manager, Cashier: the project accounts of their sub-projects.
 *  - Accountant: only the project accounts named for them.
 *  - Employee: no project accounts.
 * All limited roles see only their cash accounts, and the exchange and personal accounts named for them.
 * A person with several roles gets the union of what each allows.
 */
class LegacyDataScope
{
    public function for(User $user): DataScope
    {
        $roles = $user->roleIds();

        if ($roles === []) {
            return new DataScope(false);
        }

        foreach ([RoleId::HeadOffice, RoleId::GeneralManager, RoleId::Donor] as $unlimited) {
            if (in_array($unlimited, $roles, true)) {
                return DataScope::everything();
            }
        }

        $userId = (int) $user->getKey();
        $projects = [];

        foreach ($roles as $role) {
            $projects = [...$projects, ...match ($role) {
                RoleId::ProjectManager => [...$this->bySubProject($userId), ...$this->managedAccounts($userId)],
                RoleId::FinancialManager, RoleId::Cashier => $this->bySubProject($userId),
                RoleId::Accountant => $this->namedAccounts($userId, 'project'),
                default => [],
            }];
        }

        return new DataScope(
            unrestricted: false,
            projectAccountIds: $this->unique($projects),
            cashAccountIds: $this->unique($this->legacy()->table('user_cashaccounts')->where('user_id', $userId)->pluck('cashaccount_id')->all()),
            exchangeAccountIds: $this->unique($this->namedAccounts($userId, 'exchange')),
            personalAccountIds: $this->unique($this->namedAccounts($userId, 'personal')),
        );
    }

    /**
     * Project accounts of the sub-projects given to the person.
     *
     * @return list<int>
     */
    private function bySubProject(int $userId): array
    {
        $subProjects = $this->legacy()->table('user_accounts')->where('user_id', $userId)->whereNull('account_id')->whereNotNull('subproject_id')->pluck('subproject_id')->all();

        if ($subProjects === []) {
            return [];
        }

        return array_values(array_map('intval', $this->legacy()->table('account')->whereIn('SubProject_ID', $subProjects)->pluck('ID')->all()));
    }

    /**
     * Accounts named for the person in `user_accounts`, of one account type (project, exchange, personal).
     *
     * @return list<int>
     */
    private function namedAccounts(int $userId, string $type): array
    {
        return array_values(array_map('intval', $this->legacy()->table('user_accounts as ua')
            ->join('account as a', 'a.ID', '=', 'ua.account_id')
            ->join('accounttype as t', 't.id', '=', 'a.Accounttype_id')
            ->where('ua.user_id', $userId)
            ->whereRaw('lower(t.name) = ?', [$type])
            ->pluck('a.ID')->all()));
    }

    /**
     * Project accounts a manager was given on the web, next to the Windows program's own links.
     *
     * @return list<int>
     */
    private function managedAccounts(int $userId): array
    {
        return array_values(array_map('intval', DB::table('web_project_managers')->where('user_id', $userId)->pluck('account_id')->all()));
    }

    private function legacy(): ConnectionInterface
    {
        return DB::connection('legacy');
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function unique(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
