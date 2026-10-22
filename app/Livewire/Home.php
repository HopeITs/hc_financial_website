<?php

namespace App\Livewire;

use App\Contracts\ProjectBalanceSource;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The landing page: active projects with their balances in SYP and USD, a city filter and the key totals; the projects tree lives in the main sidebar.
 */
#[Layout('components.layouts.app')]
class Home extends Component
{
    #[Url(as: 'city')]
    public ?int $cityId = null;

    public function filterCity(?int $cityId): void
    {
        $this->cityId = $cityId === $this->cityId ? null : $cityId;
    }

    public function render(ProjectBalanceSource $balances): View
    {
        $cities = $balances->cities();

        // A city that has no active project is not a filter; an unknown id shows everything.
        $cityId = $this->cityId !== null && $cities->has($this->cityId) ? $this->cityId : null;

        return view('livewire.home', [
            'projects' => $balances->activeProjects($cityId),
            'cities' => $cities,
            'cityId' => $cityId,
        ]);
    }
}
