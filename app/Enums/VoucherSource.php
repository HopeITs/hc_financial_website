<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherSource: string
{
    case Web = 'web';
    case Migration = 'migration';
}
