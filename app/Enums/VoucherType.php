<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherType: string
{
    case Receipt = 'CRV';
    case Payment = 'CPV';
    case Transfer = 'TRF';
    // ALC is payroll: a salary split over projects (R1-2, confirmed as R5-1).
    case Allocation = 'ALC';
    // A currency exchange by a city's general account (F-03); before it, exchanges were TRF with the local_exchange kind.
    case Exchange = 'EXG';
    case Journal = 'JV';
    case Opening = 'OPN';

    public function label(): string
    {
        return __('ledger.voucher_type.'.$this->value);
    }
}
