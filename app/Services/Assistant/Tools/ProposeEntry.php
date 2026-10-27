<?php

namespace App\Services\Assistant\Tools;

use App\Enums\FundSource;
use App\Models\User;
use App\Services\Assistant\EntryProposals;
use App\Services\Ledger\OperationEntry;

/**
 * Prepares one operation of the single entry screen. It saves nothing: the person sees a card and presses Save.
 */
class ProposeEntry extends Tool
{
    public function __construct(private readonly EntryProposals $proposals) {}

    public function name(): string
    {
        return 'propose_entry';
    }

    public function allows(User $user): bool
    {
        return EntryProposals::mayEnter($user);
    }

    public function definition(): array
    {
        return [
            'description' => 'Prepare one operation as the single entry screen takes it, checked against the posting rules. It does NOT save: when it is good the person sees a card with the voucher lines and presses Save (or Cancel); the voucher is then created as pending approval, prepared by this person. When it is not good, the problems come back: fix them, or ask the person for what is missing. Kinds: income (CRV: debit the cash account, credit the income account), expense (CPV: debit the expense account, or give a budget line which fills the account and project; credit the cash account), exchange (TRF between a USD and a SYP cash account at a rate in pounds per dollar; amount is in the given account\'s currency), advance (CPV: debit 1201, the person in contact), repayment (CRV: credit 1201). The cash account\'s currency is the entry\'s currency; a SYP entry may need a rate.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'kind' => ['type' => 'string', 'enum' => OperationEntry::KINDS],
                    'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                    'cash' => ['type' => 'string', 'description' => 'Cash account code the money comes into or goes out of (for exchange: the account given).'],
                    'account' => ['type' => 'string', 'description' => 'Income account (income) or expense account (expense, unless budget_line is given).'],
                    'to_cash' => ['type' => 'string', 'description' => 'Exchange only: the cash account received, in the other currency.'],
                    'project' => ['type' => 'string', 'description' => 'Project code.'],
                    'budget_line' => ['type' => 'string', 'description' => 'Expense: budget line key; it fills the account and the project.'],
                    'amount' => ['type' => 'string', 'description' => 'Positive amount in the cash account\'s currency, digits and a dot only.'],
                    'rate' => ['type' => 'string', 'description' => 'Pounds per dollar, when the entry is in SYP or for an exchange.'],
                    'contact' => ['type' => 'string', 'description' => 'Person or supplier (required for advance and repayment).'],
                    'fund_source' => ['type' => 'string', 'enum' => array_map(fn (FundSource $source) => $source->value, FundSource::cases()), 'description' => 'Defaults to donor_grant.'],
                    'document' => ['type' => 'string', 'description' => 'Document reference, e.g. invoice number.'],
                    'description' => ['type' => 'string'],
                ],
                'required' => ['kind', 'date', 'cash', 'amount', 'description'],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        $operation = [];

        foreach (EntryProposals::FIELDS as $field) {
            $operation[$field] = $this->text($input, $field, $field === 'description' ? 500 : 100);
        }

        return $this->proposals->propose($user, $conversationId, $operation);
    }
}
