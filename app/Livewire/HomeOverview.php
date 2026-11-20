<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\User;
use App\Services\Home\HomeOverview as Overview;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Defer;
use Livewire\Component;

/**
 * The home page's overview (finance request of 2026-11-22): today's rates, the liquidity today and the donors'
 * instalments to follow, each only for the roles that see its screen. It loads by itself right after the page, like
 * the "Waiting for you" panel, so the project balances never wait for it.
 */
#[Defer]
class HomeOverview extends Component
{
    public function placeholder(): View
    {
        return view('components.home.overview-placeholder');
    }

    public function render(Overview $overview): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $today = CarbonImmutable::today();

        $showRates = $user->canOnScreen('alloc', ScreenAction::View);
        $showLiquidity = $user->canOnScreen('treasury', ScreenAction::View);
        $showFunding = $user->canOnScreen('contracts', ScreenAction::View);

        return view('livewire.home-overview', [
            'rates' => $showRates ? $overview->rates($today) : null,
            'pendingRates' => $showRates && $user->canOnScreen('alloc', ScreenAction::Approve) ? $overview->pendingRates() : 0,
            'liquidity' => $showLiquidity ? $overview->liquidity($today) : null,
            'funding' => $showFunding ? $overview->instalments($user, $today) : null,
        ]);
    }
}
