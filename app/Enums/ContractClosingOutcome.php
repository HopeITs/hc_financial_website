<?php

declare(strict_types=1);

namespace App\Enums;

/** What happens to the unspent balance when a contract is closed (finance decision 2026-11-16: all four happen). */
enum ContractClosingOutcome: string
{
    case ReturnToDonor = 'return';
    case Extension = 'extension';
    case Transfer = 'transfer';
    case KeepUnrestricted = 'keep';

    public function label(): string
    {
        return __('contracts.closing_outcomes.'.$this->value);
    }
}
