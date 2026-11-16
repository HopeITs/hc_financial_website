<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a funding contract stands. Draft until signed; it runs between its dates; it ends when closed, or is cancelled. */
enum ContractStatus: string
{
    case Draft = 'draft';
    case Signed = 'signed';
    case Running = 'running';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('contracts.statuses.'.$this->value);
    }

    /** The badge colour: green while the contract is in force, sky for a draft, zinc once it is over. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Signed, self::Running => 'green',
            self::Draft => 'sky',
            self::Ended, self::Cancelled => 'zinc',
        };
    }
}
