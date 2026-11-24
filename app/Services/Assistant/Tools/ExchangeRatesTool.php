<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\MonthlyRates;
use Carbon\CarbonImmutable;

/**
 * The exchange rates in force on a day: the approved daily rate and the month's rate of each currency.
 */
class ExchangeRatesTool extends Tool
{
    public function __construct(private readonly DailyRates $daily, private readonly MonthlyRates $monthly) {}

    public function name(): string
    {
        return 'exchange_rates';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'alloc') || $this->can($user, 'treasury');
    }

    public function definition(): array
    {
        return [
            'description' => 'For each currency other than USD, on a day: the approved daily rate in force (and the day it was set) and the monthly rate (provisional when it is a previous month\'s). Rates are units of the currency per dollar.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'day' => ['type' => 'string', 'description' => 'Day YYYY-MM-DD; leave empty for today.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $day = CarbonImmutable::parse($this->day($input, 'day') ?? now()->toDateString());
        $rates = [];

        foreach ($this->daily->currencies() as $currency) {
            $daily = $this->daily->rateFor($currency, $day);
            $rates[$currency] = [
                'daily_rate' => $daily?->rate,
                'daily_rate_day' => $daily?->day?->toDateString(),
                'monthly' => $this->monthly->forDay($currency, $day),
            ];
        }

        return ['day' => $day->toDateString(), 'rates' => $rates];
    }
}
