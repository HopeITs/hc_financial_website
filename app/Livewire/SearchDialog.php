<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Search\GlobalSearch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The search window (U-06): opened with Ctrl+K or the search button in the top bar, it finds vouchers, accounts,
 * projects and screens as the person types. Enter opens the highlighted result, so a full voucher number opens it.
 */
class SearchDialog extends Component
{
    public string $term = '';

    public function render(GlobalSearch $search): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.search-dialog', [
            'groups' => $search->search($user, $this->term),
            'tooShort' => mb_strlen(trim($this->term)) < GlobalSearch::MIN_LENGTH,
        ]);
    }
}
