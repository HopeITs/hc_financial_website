<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Ledger\TreasuryPosition;

/**
 * Where the money is, city by city, as the treasury screen shows it.
 */
class TreasuryTool extends Tool
{
    public function __construct(private readonly TreasuryPosition $treasury) {}

    public function name(): string
    {
        return 'treasury_position';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'treasury');
    }

    public function definition(): array
    {
        return [
            'description' => 'The treasury position from approved vouchers, as at a day or now: for each city the dollars and pounds held by its general accounts, the pounds\' book value in USD and average rate, the pounds bought, the cash in transit, the exchange reserve, and each cash account (safe) with its balance in its own currency and in USD; plus the cash with no city (banks, transfer company) and the totals.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'as_at' => ['type' => 'string', 'description' => 'Day YYYY-MM-DD; leave empty for now.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $report = $this->treasury->at($this->day($input, 'as_at'));

        /** @var array<string, mixed> $position */
        $position = json_decode((string) json_encode($report), true);

        return $position + [
            'basis' => 'approved vouchers only',
            'total_usd_held' => $report->totalUsdHeld(),
            'total_syp_held' => $report->totalSypHeld(),
            'total_in_transit_usd' => $report->totalInTransitUsd(),
        ];
    }
}
