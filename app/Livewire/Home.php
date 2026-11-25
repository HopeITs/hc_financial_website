<?php

namespace App\Livewire;

use App\Data\ProjectCash;
use App\Livewire\Concerns\DefersLoading;
use App\Models\User;
use App\Services\Projects\ActiveProjectBalances;
use App\Services\Projects\ProjectAccess;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The landing page: active projects with the cash they hold in SYP and USD (from the ledger), a city filter and the key totals.
 */
#[Layout('components.layouts.app')]
class Home extends Component
{
    use DefersLoading;

    #[Url(as: 'city', except: '')]
    public string $city = '';

    public function render(ActiveProjectBalances $balances, ProjectAccess $access): View
    {
        $user = auth()->user();

        // The home page follows the role (finance decision of 2026-11-25): the projects' balances only for those who
        // see the projects' money; the others get what waits for them and their quick actions, without that read.
        if (! $user instanceof User || ! $access->seesMoney($user)) {
            return view('livewire.home', ['showMoney' => false, 'projects' => collect(), 'cities' => collect(), 'actions' => $this->quickActions()]);
        }

        $limit = $access->limitedTo($user);
        $scope = md5((string) json_encode($limit));
        $cities = collect(Cache::remember('home.cities.'.$scope, self::CACHE_SECONDS, fn (): array => $balances->cities($limit)->all()));

        // A city that has no active project is not a filter; an unknown one shows everything.
        $city = $this->city !== '' && $cities->has($this->city) ? $this->city : null;

        return view('livewire.home', [
            'showMoney' => true,
            'projects' => $this->activeProjects($balances, $city, $limit, $scope),
            'cities' => $cities,
            'actions' => $this->quickActions(),
        ]);
    }

    /**
     * The active projects' cash, kept for a minute: the page asks again at every click (finance request of
     * 2026-11-24). The cache holds plain arrays only, as the cache store unserializes no classes.
     *
     * @param  list<string>|null  $limit
     * @return Collection<int, ProjectCash>
     */
    private function activeProjects(ActiveProjectBalances $balances, ?string $city, ?array $limit, string $scope): Collection
    {
        $rows = Cache::remember(
            'home.projects.'.$scope.'.'.md5($city ?? ''),
            self::CACHE_SECONDS,
            fn (): array => $balances->activeProjects($city, $limit)->map(fn (ProjectCash $balance): array => get_object_vars($balance))->all(),
        );

        return collect($rows)->map(fn (array $row): ProjectCash => new ProjectCash(...$row))->values();
    }

    /** How long the balances stay before they are read again, in seconds. */
    private const CACHE_SECONDS = 60;

    /**
     * The everyday entries the person may make, as buttons (finance request of 2026-11-22).
     *
     * @return list<array{slug: string, icon: string, url: string}>
     */
    private function quickActions(): array
    {
        $user = auth()->user();

        return Navigation::creatable($user instanceof User ? $user : null, self::QUICK_ACTIONS);
    }

    /** The quick actions, screen slug => icon, in the order they show. */
    private const QUICK_ACTIONS = [
        'purchases' => 'shopping-cart',
        'advance' => 'hand-raised',
        'transfers' => 'arrows-right-left',
        'payments' => 'banknotes',
        'journal' => 'book-open',
        'tills' => 'calculator',
    ];
}
