<?php

namespace App\Services\Home;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\DailyRate;
use App\Models\User;
use App\Services\Contracts\ContractReceipts;
use App\Services\Projects\ProjectAccess;
use App\Services\Treasury\CashBook;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\DailyValuation;
use App\Services\Treasury\MonthlyRates;
use Carbon\CarbonImmutable;

/**
 * The home page's overview (finance request of 2026-11-22), each part only for the roles that see its screen:
 *
 * - today's rates: the approved daily rate of each currency (and whether it is today's), and the month's pound rate
 *   (and whether it is still provisional);
 * - the liquidity today: where the money is, by group, at today's value;
 * - the donors' instalments still expected in the next days and the late ones, and the agreements ending soon.
 */
class HomeOverview
{
    /** Instalments due within this many days are listed as coming. */
    public const COMING_DAYS = 30;

    /** Agreements ending within this many days are listed. */
    public const ENDING_DAYS = 60;

    /** The most instalments listed. */
    public const MAX_INSTALMENTS = 8;

    public function __construct(
        private readonly DailyRates $daily,
        private readonly MonthlyRates $monthly,
        private readonly CashBook $book,
        private readonly ContractReceipts $receipts,
        private readonly ProjectAccess $access,
    ) {}

    /**
     * @return list<array{currency: string, rate: string|null, kind: string, as_of: string|null, stale: bool, provisional: bool}>
     */
    public function rates(CarbonImmutable $today): array
    {
        $rates = [];

        foreach ($this->daily->currencies() as $currency) {
            if ($currency === DailyValuation::MONTHLY_CURRENCY) {
                $month = $this->monthly->forDay($currency, $today);
                $rates[] = [
                    'currency' => $currency,
                    'rate' => $month !== null ? $this->trim($month['rate']) : null,
                    'kind' => 'monthly',
                    'as_of' => $month !== null ? CarbonImmutable::parse($month['month'].'-01')->format('m/Y') : null,
                    'stale' => false,
                    'provisional' => (bool) ($month['provisional'] ?? false),
                ];

                continue;
            }

            $day = $this->daily->rateFor($currency, $today);
            $rates[] = [
                'currency' => $currency,
                'rate' => $day !== null ? $this->trim((string) $day->rate) : null,
                'kind' => 'daily',
                'as_of' => $day?->day->format('d/m/Y'),
                'stale' => $day !== null && ! $day->day->isSameDay($today),
                'provisional' => false,
            ];
        }

        return $rates;
    }

    /** How many daily rates wait for the Financial Manager. */
    public function pendingRates(): int
    {
        return DailyRate::query()->where('status', DailyRate::PENDING)->count();
    }

    /**
     * @return array{groups: list<array{key: string, label: string, value: string, accounts: int}>, total_value: string, total_usd: string, missing_rates: list<string>}
     */
    public function liquidity(CarbonImmutable $today): array
    {
        $position = $this->book->position($today);

        return [
            'groups' => array_map(fn (array $group): array => [
                'key' => $group['key'],
                'label' => match (true) {
                    $group['key'] === 'banks' => __('treasury.group_banks'),
                    $group['key'] === 'company' => __('treasury.group_company'),
                    default => __('treasury.group_city', ['city' => substr($group['key'], 5)]),
                },
                'value' => $group['value'],
                'accounts' => count($group['rows']),
            ], $position['groups']),
            'total_value' => $position['total_value'],
            'total_usd' => $position['total_usd'],
            'missing_rates' => $position['missing_rates'],
        ];
    }

    /**
     * The instalments of the open agreements the person may see that still wait for money: the late ones first, then
     * those due in the coming days; and the agreements ending soon.
     *
     * @return array{instalments: list<array{contract: int, number: string, title: string, donor: string, instalment: int, due: string, days: int, late: bool, conditional: bool, remaining: string, pending: string, currency: string}>, ending: list<array{contract: int, number: string, title: string, ends: string, days: int}>, late_count: int}
     */
    public function instalments(User $user, CarbonImmutable $today): array
    {
        $contracts = Contract::query()
            ->visibleTo($this->access->limitedTo($user))
            ->whereNotNull('approved_at')
            ->whereNotIn('status', [ContractStatus::Ended->value, ContractStatus::Cancelled->value])
            ->with(['donor', 'instalments'])
            ->get();

        $horizon = $today->addDays(self::COMING_DAYS);
        $arabic = app()->getLocale() === 'ar';
        $rows = [];

        foreach ($contracts as $contract) {
            $status = $this->receipts->status($contract);

            foreach ($contract->instalments as $instalment) {
                $state = $status[$instalment->id] ?? null;
                if ($state === null || bccomp($state['remaining'], '0', 2) !== 1 || $instalment->due_on->gt($horizon)) {
                    continue;
                }

                $late = $instalment->isRecognised() && $instalment->due_on->lt($today);
                $rows[] = [
                    'contract' => $contract->id,
                    'number' => (string) $contract->number,
                    'title' => (string) $contract->title,
                    'donor' => (string) ($arabic ? ($contract->donor?->name_ar ?: $contract->donor?->name_en) : $contract->donor?->name_en),
                    'instalment' => (int) $instalment->number,
                    'due' => $instalment->due_on->format('d/m/Y'),
                    'days' => (int) abs($today->diffInDays($instalment->due_on, false)),
                    'late' => $late,
                    'conditional' => ! $instalment->isRecognised(),
                    'remaining' => $state['remaining'],
                    'pending' => $state['pending'],
                    'currency' => (string) $contract->currency,
                    'sort' => $instalment->due_on->toDateString(),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => [! $a['late'], $a['sort']] <=> [! $b['late'], $b['sort']]);
        $lateCount = count(array_filter($rows, fn (array $row): bool => $row['late']));

        $ending = $contracts
            ->filter(fn (Contract $contract): bool => $contract->ends_on !== null && $contract->ends_on->betweenIncluded($today, $today->addDays(self::ENDING_DAYS)))
            ->sortBy('ends_on')
            ->map(fn (Contract $contract): array => [
                'contract' => $contract->id,
                'number' => (string) $contract->number,
                'title' => (string) $contract->title,
                'ends' => $contract->ends_on->format('d/m/Y'),
                'days' => (int) $today->diffInDays($contract->ends_on),
            ])
            ->values()->all();

        return [
            'instalments' => array_map(function (array $row): array {
                unset($row['sort']);

                return $row;
            }, array_slice($rows, 0, self::MAX_INSTALMENTS)),
            'ending' => $ending,
            'late_count' => $lateCount,
        ];
    }

    private function trim(string $rate): string
    {
        return rtrim(rtrim($rate, '0'), '.');
    }
}
