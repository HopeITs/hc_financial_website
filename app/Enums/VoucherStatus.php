<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Replaced = 'replaced';

    public function label(): string
    {
        return __('ledger.voucher_status.'.$this->value);
    }
}
