<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Home\WaitingWork;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Defer;
use Livewire\Component;

/**
 * The "Waiting for you" panel at the top of the home page (U-03). It loads by itself right after the page, so the
 * project balances never wait for it.
 */
#[Defer]
class HomeWaiting extends Component
{
    public function placeholder(): View
    {
        return view('components.home.waiting-placeholder');
    }

    public function render(WaitingWork $waiting): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.home-waiting', ['items' => $waiting->for($user)]);
    }
}
