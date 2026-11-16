<?php

declare(strict_types=1);

namespace App\Enums;

/** Whether the donor ties the money of a contract to its projects. */
enum ContractRestriction: string
{
    case Restricted = 'restricted';
    case Unrestricted = 'unrestricted';

    public function label(): string
    {
        return __('contracts.restrictions.'.$this->value);
    }
}
