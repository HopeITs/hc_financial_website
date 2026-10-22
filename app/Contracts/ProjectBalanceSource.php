<?php

namespace App\Contracts;

use App\Data\CashAccountBalance;
use App\Data\ProjectBalance;
use Illuminate\Support\Collection;

/**
 * Where the balances of the active projects come from. For now the Windows program's tables;
 * a second implementation reads the ledger, and the binding changes without touching the screens.
 */
interface ProjectBalanceSource
{
    /**
     * Active projects with their balances, by name; only one city's when `$cityId` is given.
     *
     * @return Collection<int, ProjectBalance>
     */
    public function activeProjects(?int $cityId = null): Collection;

    /**
     * The cities that have at least one active project, for the filter: id => name.
     *
     * @return Collection<int, string>
     */
    public function cities(): Collection;

    /**
     * The balances of the given project accounts, closed ones included, keyed by account id.
     * An account with no money has no entry.
     *
     * @param  array<int, int>  $accountIds
     * @return Collection<int, ProjectBalance>
     */
    public function balancesFor(array $accountIds): Collection;

    /**
     * Where one project's money is: its balance in each cash account.
     *
     * @return Collection<int, CashAccountBalance>
     */
    public function byCashAccount(int $accountId): Collection;
}
