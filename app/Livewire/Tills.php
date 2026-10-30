<?php

namespace App\Livewire;

use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\TillOwners;
use App\Services\Projects\ProjectAccess;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cash tills (F-08): one block per cash account with its balance from approved ledger lines, and under it every project and
 * general account that owns part of it. A person limited to some projects sees only those owners' rows, and not the
 * balance or the unowned part, which would show other projects' money.
 */
#[Layout('components.layouts.app')]
class Tills extends Component
{
    public function render(TillOwners $owners, ProjectAccess $access): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $limit = $access->limitedTo($user);
        $codes = $limit === null ? null : array_values(GlProject::query()->whereIn('legacy_account_id', $limit)->pluck('code')->all());

        $tills = $owners->tills($codes);

        if ($codes !== null) {
            $tills = array_values(array_filter($tills, fn (array $till) => $till['owners'] !== []));
        }

        return view('livewire.tills', ['tills' => $tills, 'limited' => $codes !== null]);
    }
}
