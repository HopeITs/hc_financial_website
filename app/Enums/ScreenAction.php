<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a role may do on a screen. The accountant prepares, the financial manager approves.
 */
enum ScreenAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';
    case Approve = 'approve';
}
