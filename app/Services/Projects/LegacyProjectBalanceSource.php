<?php

namespace App\Services\Projects;

use App\Contracts\ProjectBalanceSource;
use App\Data\CashAccountBalance;
use App\Data\ProjectBalance;
use App\Models\Legacy\CashBalance;
use Illuminate\Support\Collection;

/**
 * Balances read from the legacy `cash_balances` view: one row per cash account and project account,
 * so a project's balance is the sum of its rows. Old and new lira are kept apart.
 */
class LegacyProjectBalanceSource implements ProjectBalanceSource
{
    public function activeProjects(?int $cityId = null, ?array $limitedTo = null): Collection
    {
        return CashBalance::query()
            ->where('account_IsClosed', 0)
            ->where('Account_Type', 'Project')
            ->when($cityId !== null, fn ($query) => $query->where('City_ID', $cityId))
            ->when($limitedTo !== null, fn ($query) => $query->whereIn('Account_ID', $limitedTo))
            ->selectRaw('Account_ID, MAX(Account_Name) as name, MAX(City_ID) as city_id, MAX(City_Name) as city_name,
                MAX(Subproject_Name) as sub_project, SUM(Account_Net) as syp, SUM(Account_Net_New) as syp_new, SUM(Account_Net_USD) as usd')
            ->groupBy('Account_ID')
            ->orderBy('name')
            ->get()
            ->map(fn (CashBalance $row): ProjectBalance => $this->balance($row))
            ->values();
    }

    public function balancesFor(array $accountIds): Collection
    {
        if ($accountIds === []) {
            return collect();
        }

        return CashBalance::query()
            ->where('Account_Type', 'Project')
            ->whereIn('Account_ID', $accountIds)
            ->selectRaw('Account_ID, MAX(Account_Name) as name, MAX(City_ID) as city_id, MAX(City_Name) as city_name,
                MAX(Subproject_Name) as sub_project, SUM(Account_Net) as syp, SUM(Account_Net_New) as syp_new, SUM(Account_Net_USD) as usd')
            ->groupBy('Account_ID')
            ->get()
            ->mapWithKeys(fn (CashBalance $row): array => [(int) $row->Account_ID => $this->balance($row)]);
    }

    public function byCashAccount(int $accountId, ?array $cashAccountIds = null): Collection
    {
        return CashBalance::query()
            ->where('Account_ID', $accountId)
            ->when($cashAccountIds !== null, fn ($query) => $query->whereIn('CashAccount_id', $cashAccountIds))
            ->selectRaw('CashAccount_id, MAX(CashAccount_Name) as name, SUM(Account_Net) as syp, SUM(Account_Net_New) as syp_new, SUM(Account_Net_USD) as usd')
            ->groupBy('CashAccount_id')
            ->orderBy('name')
            ->get()
            ->map(fn (CashBalance $row): CashAccountBalance => new CashAccountBalance(
                cashAccountId: (int) $row->CashAccount_id,
                name: (string) $row->getAttribute('name'),
                syp: $this->money($row->getAttribute('syp')),
                sypNew: $this->money($row->getAttribute('syp_new')),
                usd: $this->money($row->getAttribute('usd')),
            ))
            ->values();
    }

    private function balance(CashBalance $row): ProjectBalance
    {
        return new ProjectBalance(
            accountId: (int) $row->Account_ID,
            name: (string) $row->getAttribute('name'),
            cityId: $row->getAttribute('city_id') === null ? null : (int) $row->getAttribute('city_id'),
            cityName: $row->getAttribute('city_name'),
            subProjectName: $row->getAttribute('sub_project'),
            syp: $this->money($row->getAttribute('syp')),
            sypNew: $this->money($row->getAttribute('syp_new')),
            usd: $this->money($row->getAttribute('usd')),
        );
    }

    public function cities(?array $limitedTo = null): Collection
    {
        return CashBalance::query()
            ->where('account_IsClosed', 0)
            ->where('Account_Type', 'Project')
            ->when($limitedTo !== null, fn ($query) => $query->whereIn('Account_ID', $limitedTo))
            ->whereNotNull('City_ID')
            ->selectRaw('City_ID, MAX(City_Name) as city_name')
            ->groupBy('City_ID')
            ->orderBy('city_name')
            ->get()
            ->mapWithKeys(fn (CashBalance $row): array => [(int) $row->City_ID => (string) $row->getAttribute('city_name')]);
    }

    /**
     * The view's columns are doubles; money leaves here as a two-decimal string.
     *
     * @return numeric-string
     */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
