<?php

declare(strict_types=1);

namespace App\Enums;

/** The four kinds of transfer voucher (Financial Manager, round 2); moving project cash to city cash is not one of them. */
enum TransferKind: string
{
    case UsdToProject = 'usd_to_project';
    case SypToProject = 'syp_to_project';
    case Return = 'return';
    case BetweenProjects = 'between_projects';

    public function label(): string
    {
        return __('ledger.transfers.kind.'.$this->value);
    }

    /** Whether the kind moves money out of a project (and so needs a source project). */
    public function movesFromProject(): bool
    {
        return $this === self::Return || $this === self::BetweenProjects;
    }
}
