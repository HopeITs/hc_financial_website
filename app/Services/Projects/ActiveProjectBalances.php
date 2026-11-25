<?php

namespace App\Services\Projects;

use App\Data\ProjectCash;
use App\Models\GlProject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cash each active project holds, for the home page, read from the ledger's approved vouchers (finance decision
 * of 2026-11-27, leaving the Windows program: its `cash_balances` view stops moving once it is switched off).
 *
 * A project is active while it has not ended, and stays on the list after its end date while it still holds cash. The
 * pounds and the dollars are each the cash lines of that currency as entered; euros are shown in dollars beside them.
 */
class ActiveProjectBalances
{
    /**
     * The active projects with their cash, by name; only one city's when given.
     *
     * @param  list<string>|null  $limitedTo  the codes the person may see, or null for all
     * @return Collection<int, ProjectCash>
     */
    public function activeProjects(?string $city = null, ?array $limitedTo = null): Collection
    {
        $cash = $this->cash();
        $today = CarbonImmutable::today()->toDateString();

        return GlProject::query()->where('is_general_account', false)
            ->when($limitedTo !== null, fn ($query) => $query->whereIn('code', $limitedTo ?? []))
            ->when($city !== null, fn ($query) => $query->where('city', $city))
            ->orderBy('name')
            ->get(['code', 'name', 'city', 'end_date'])
            ->map(function (GlProject $project) use ($cash, $today): ?ProjectCash {
                $held = $cash[$project->code] ?? [];
                $balance = new ProjectCash(
                    code: $project->code,
                    name: (string) $project->name,
                    city: $project->city !== null && $project->city !== '' ? (string) $project->city : null,
                    syp: $held['SYP'] ?? '0.00',
                    usd: $held['USD'] ?? '0.00',
                    otherUsd: $held['other_usd'] ?? '0.00',
                );
                $ended = $project->end_date !== null && $project->end_date->toDateString() < $today;

                return ! $ended || $balance->holdsCash() ? $balance : null;
            })
            ->filter()
            ->values();
    }

    /**
     * The cities that have at least one active project, for the filter: city => city.
     *
     * @param  list<string>|null  $limitedTo
     * @return Collection<string, string>
     */
    public function cities(?array $limitedTo = null): Collection
    {
        return $this->activeProjects(null, $limitedTo)->pluck('city')->filter()->unique()->sort()
            ->mapWithKeys(fn (string $city): array => [$city => $city]);
    }

    /**
     * The cash of every project from the approved vouchers: project => SYP and USD as entered, any other currency in dollars.
     *
     * @return array<string, array<string, string>>
     */
    private function cash(): array
    {
        $rows = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('v.status', 'approved')
            ->whereNull('v.deleted_at')
            ->where('a.is_cash', true)
            ->whereNotNull('l.project_code')
            ->groupBy('l.project_code', 'l.currency')
            ->selectRaw('l.project_code, l.currency, sum(l.debit_orig) - sum(l.credit_orig) as orig, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->get();

        $cash = [];
        foreach ($rows as $row) {
            $code = (string) $row->project_code;
            if (in_array($row->currency, ['SYP', 'USD'], true)) {
                $cash[$code][$row->currency] = bcadd($cash[$code][$row->currency] ?? '0.00', $this->money($row->orig), 2);
            } else {
                $cash[$code]['other_usd'] = bcadd($cash[$code]['other_usd'] ?? '0.00', $this->money($row->usd), 2);
            }
        }

        return $cash;
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        return is_numeric($value) ? bcadd((string) $value, '0', 2) : '0.00';
    }
}
