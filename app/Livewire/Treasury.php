<?php

namespace App\Livewire;

use App\Services\Ledger\TreasuryPosition;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Where the money is, city by city, as at a day: general accounts, bulk purchases of pounds, safes, cash in transit, reserve. */
#[Layout('components.layouts.app')]
class Treasury extends Component
{
    #[Url]
    public string $asAt = '';

    public function render(TreasuryPosition $position): View
    {
        $asAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->asAt) === 1 ? $this->asAt : null;

        return view('livewire.treasury', ['report' => $position->at($asAt)]);
    }
}
