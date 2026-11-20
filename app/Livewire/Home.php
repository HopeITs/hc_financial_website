<?php

namespace App\Livewire;

use App\Contracts\ProjectBalanceSource;
use App\Enums\ScreenAction;
use App\Livewire\Concerns\DefersLoading;
use App\Models\User;
use App\Services\Projects\ProjectAccess;
use App\Support\Navigation;
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
    use DefersLoading;

    #[Url(as: 'city')]
    public ?int $cityId = null;

    public function filterCity(?int $cityId): void
    {
        $this->cityId = $cityId === $this->cityId ? null : $cityId;
    }

    public function render(ProjectBalanceSource $balances, ProjectAccess $access): View
    {
        $limit = $access->limitedTo(auth()->user());
        $cities = $balances->cities($limit);

        // A city that has no active project is not a filter; an unknown id shows everything.
        $cityId = $this->cityId !== null && $cities->has($this->cityId) ? $this->cityId : null;

        return view('livewire.home', [
            'projects' => $balances->activeProjects($cityId, $limit),
            'cities' => $cities,
            'cityId' => $cityId,
            'actions' => $this->quickActions(),
        ]);
    }

    /**
     * The everyday entries the person may make, as buttons (finance request of 2026-11-22).
     *
     * @return list<array{slug: string, icon: string, url: string}>
     */
    private function quickActions(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $actions = [];
        foreach (self::QUICK_ACTIONS as $slug => $icon) {
            if ($user->canOnScreen($slug, ScreenAction::Create)) {
                $actions[] = ['slug' => $slug, 'icon' => $icon, 'url' => route(Navigation::routeName($slug))];
            }
        }

        return $actions;
    }

    /** The quick actions, screen slug => icon, in the order they show. */
    private const QUICK_ACTIONS = [
        'single' => 'arrow-up-circle',
        'receipt' => 'arrow-down-circle',
        'journal' => 'book-open',
        'multi' => 'table-cells',
        'transfers' => 'arrows-right-left',
        'tills' => 'calculator',
    ];
}
