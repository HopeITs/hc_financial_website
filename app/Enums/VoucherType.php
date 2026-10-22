<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherType: string
{
    case Receipt = 'CRV';
    case Payment = 'CPV';
    case Transfer = 'TRF';
    case Allocation = 'ALC';
    case Journal = 'JV';
    case Opening = 'OPN';

    public function label(): string
    {
        return __('ledger.voucher_type.'.$this->value);
    }
}
