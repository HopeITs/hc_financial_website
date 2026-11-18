<?php

namespace App\Livewire;

use App\Services\Entries\CashVouchers;
use Livewire\Attributes\Layout;

/** The receipt voucher (سند قبض): money received into a cash or bank account. Donor instalments are received from their agreement. */
#[Layout('components.layouts.app')]
class ReceiptVoucher extends CashVoucherForm
{
    protected function kind(): string
    {
        return CashVouchers::RECEIPT;
    }

    protected function screen(): string
    {
        return 'receipt';
    }
}
