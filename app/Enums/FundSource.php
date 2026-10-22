<?php

declare(strict_types=1);

namespace App\Enums;

enum FundSource: string
{
    case DonorGrant = 'donor_grant';
    case ChcOwnFunds = 'chc_own_funds';
    case Treasury = 'treasury';
    case OwnIncome = 'own_income';
    case RecoveredLoans = 'recovered_loans';

    public function label(): string
    {
        return __('ledger.fund_source.'.$this->value);
    }
}
