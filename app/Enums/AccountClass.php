<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountClass: string
{
    case Assets = 'assets';
    case Liabilities = 'liabilities';
    case NetAssets = 'net_assets';
    case Income = 'income';
    case Expenses = 'expenses';

    public function label(): string
    {
        return __('ledger.class.'.$this->value);
    }
}
