<?php

namespace App\Livewire;

use App\Models\GlAccount;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The chart of accounts: the five classes, or one account with its path, rules and the accounts below it.
 * The tree to pick an account lives in the main sidebar.
 */
#[Layout('components.layouts.app')]
class Chart extends Component
{
    #[Url(as: 'account')]
    public ?string $code = null;

    public function render(): View
    {
        $account = $this->code !== null ? GlAccount::query()->where('code', $this->code)->first() : null;

        $path = [];
        for ($step = $account; $step !== null; $step = $step->parent_code ? GlAccount::query()->where('code', $step->parent_code)->first() : null) {
            array_unshift($path, $step);
        }

        $roots = GlAccount::query()->whereNull('parent_code')->orderBy('code')->get();
        $postingCounts = GlAccount::query()->where('level', 'posting')->selectRaw('class, count(*) as total')->groupBy('class')->pluck('total', 'class');

        return view('livewire.chart', [
            'account' => $account,
            'path' => $path,
            'children' => $account ? GlAccount::query()->where('parent_code', $account->code)->orderBy('code')->get() : collect(),
            'roots' => $roots,
            'postingCounts' => $postingCounts,
            'total' => GlAccount::query()->count(),
        ]);
    }
}
