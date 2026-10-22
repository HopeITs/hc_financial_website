<?php

namespace App\Livewire;

use App\Contracts\ProjectBalanceSource;
use App\Services\Projects\SideTree;
use App\Support\UserPreferences;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The landing page: active projects with their balances in SYP and USD, a city filter and the side tree.
 */
#[Layout('components.layouts.app')]
class Home extends Component
{
    #[Url(as: 'city')]
    public ?int $cityId = null;

    public int $sidebarWidth = UserPreferences::DEFAULT_SIDEBAR_WIDTH;

    public function mount(UserPreferences $preferences): void
    {
        $this->sidebarWidth = $preferences->sidebarWidth((int) auth()->id());
    }

    public function filterCity(?int $cityId): void
    {
        $this->cityId = $cityId === $this->cityId ? null : $cityId;
    }

    public function saveSidebarWidth(int $width, UserPreferences $preferences): void
    {
        $this->sidebarWidth = $preferences->setSidebarWidth((int) auth()->id(), $width);
    }

    public function render(ProjectBalanceSource $balances, SideTree $tree): View
    {
        $cities = $balances->cities();

        // A city that has no active project is not a filter; an unknown id shows everything.
        $cityId = $this->cityId !== null && $cities->has($this->cityId) ? $this->cityId : null;

        return view('livewire.home', [
            'projects' => $balances->activeProjects($cityId),
            'cities' => $cities,
            'cityId' => $cityId,
            'tree' => $tree->cities(),
            'minWidth' => UserPreferences::MIN_SIDEBAR_WIDTH,
            'maxWidth' => UserPreferences::MAX_SIDEBAR_WIDTH,
        ]);
    }
}
