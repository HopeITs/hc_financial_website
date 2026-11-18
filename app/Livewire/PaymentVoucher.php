<?php

namespace App\Livewire;

use App\Services\Entries\CashVouchers;
use Livewire\Attributes\Layout;

/** The payment voucher (سند صرف): money paid out of a cash or bank account, for invoices and expenses. */
#[Layout('components.layouts.app')]
class PaymentVoucher extends CashVoucherForm
{
    protected function kind(): string
    {
        return CashVouchers::PAYMENT;
    }

    /** It keeps the permission of the old single-entry screen, so who may enter payments does not change. */
    protected function screen(): string
    {
        return 'single';
    }
}
