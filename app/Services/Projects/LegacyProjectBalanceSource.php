<?php

namespace App\Services\Projects;

use App\Contracts\ProjectBalanceSource;
use App\Data\ProjectBalance;
use App\Models\Legacy\CashBalance;
use Illuminate\Support\Collection;

/**
 * Balances read from the legacy `cash_balances` view: one row per cash account and project account,
 * so a project's balance is the sum of its rows. Old and new lira are kept apart.
 */
class LegacyProjectBalanceSource implements ProjectBalanceSource
{
    public function activeProjects(?int $cityId = null): Collection
    {
        return CashBalance::query()
            ->where('account_IsClosed', 0)
            ->where('Account_Type', 'Project')
            ->when($cityId !== null, fn ($query) => $query->where('City_ID', $cityId))
            ->selectRaw('Account_ID, MAX(Account_Name) as name, MAX(City_ID) as city_id, MAX(City_Name) as city_name,
                MAX(Subproject_Name) as sub_project, SUM(Account_Net) as syp, SUM(Account_Net_New) as syp_new, SUM(Account_Net_USD) as usd')
            ->groupBy('Account_ID')
            ->orderBy('name')
            ->get()
            ->map(fn (CashBalance $row): ProjectBalance => new ProjectBalance(
                accountId: (int) $row->Account_ID,
                name: (string) $row->getAttribute('name'),
                cityId: $row->getAttribute('city_id') === null ? null : (int) $row->getAttribute('city_id'),
                cityName: $row->getAttribute('city_name'),
                subProjectName: $row->getAttribute('sub_project'),
                syp: $this->money($row->getAttribute('syp')),
                sypNew: $this->money($row->getAttribute('syp_new')),
                usd: $this->money($row->getAttribute('usd')),
            ))
            ->values();
    }

    public function cities(): Collection
    {
        return CashBalance::query()
            ->where('account_IsClosed', 0)
            ->where('Account_Type', 'Project')
            ->whereNotNull('City_ID')
            ->selectRaw('City_ID, MAX(City_Name) as city_name')
            ->groupBy('City_ID')
            ->orderBy('city_name')
            ->get()
            ->mapWithKeys(fn (CashBalance $row): array => [(int) $row->City_ID => (string) $row->getAttribute('city_name')]);
    }

    /**
     * The view's columns are doubles; money leaves here as a two-decimal string.
     */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
