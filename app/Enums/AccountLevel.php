<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountLevel: string
{
    case Header = 'header';
    case Posting = 'posting';

    public function label(): string
    {
        return __('ledger.level.'.$this->value);
    }
}
