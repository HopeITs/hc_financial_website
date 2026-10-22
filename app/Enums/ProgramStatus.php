<?php

declare(strict_types=1);

namespace App\Enums;

enum ProgramStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('programs.status.'.$this->value);
    }
}
