<?php

declare(strict_types=1);

namespace App\Enums;

enum NormalSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return __('ledger.side.'.$this->value);
    }
}
