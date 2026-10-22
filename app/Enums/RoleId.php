<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The roles of the web app. Ids 2 to 8 are the Windows program's `role` rows;
 * the General Manager is web-only (held in `web_user_roles`) and uses an id outside the legacy range.
 */
enum RoleId: int
{
    case HeadOffice = 2;
    case ProjectManager = 3;
    case FinancialManager = 4;
    case Accountant = 5;
    case Cashier = 6;
    case Employee = 7;
    case Donor = 8;
    case GeneralManager = 100;

    /**
     * The role is stored in the web table, not in the legacy `role` table.
     */
    public function isWebOnly(): bool
    {
        return $this === self::GeneralManager;
    }

    public function label(): string
    {
        return __('roles.'.$this->name);
    }
}
