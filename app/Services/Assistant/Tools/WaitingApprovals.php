<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Home\ApprovalsInbox;

/**
 * What waits for the person's approval, as the "Waiting for my approval" screen lists it.
 */
class WaitingApprovals extends Tool
{
    public function __construct(private readonly ApprovalsInbox $inbox) {}

    public function name(): string
    {
        return 'waiting_for_my_approval';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'inbox');
    }

    public function definition(): array
    {
        return [
            'description' => 'Everything waiting for this person\'s approval, by kind: vouchers, project requests, purchase orders, payrolls and micro-project payment orders, each with its title, detail, amount and currency. The assistant cannot approve: the person approves on the "Waiting for my approval" screen.',
            'inputSchema' => ['type' => 'object', 'properties' => (object) []],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $result = [];

        foreach ($this->inbox->for($user) as $kind => $items) {
            $result[$kind] = array_map(fn (array $item): array => [
                'id' => $item['id'], 'title' => $item['title'], 'detail' => $item['detail'], 'amount' => $item['amount'], 'currency' => $item['currency'],
            ], array_slice($items, 0, $this->maxRows()));
        }

        return ['waiting' => $result, 'total' => array_sum(array_map('count', $this->inbox->for($user)))];
    }
}
