<?php

namespace App\Livewire;

use Livewire\Attributes\Url;

/** The old approval screen: the voucher list opened on the vouchers that wait for approval. */
class Approval extends Vouchers
{
    #[Url]
    public string $status = 'pending_approval';
}
