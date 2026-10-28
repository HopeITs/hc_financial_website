<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of transfer voucher (Financial Manager, round 2; local exchange added in round 4, R4-9); moving project cash to
 * city cash is not one of them.
 */
enum TransferKind: string
{
    case UsdToProject = 'usd_to_project';
    case SypToProject = 'syp_to_project';
    case Return = 'return';
    case BetweenProjects = 'between_projects';
    case LocalExchange = 'local_exchange';

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
