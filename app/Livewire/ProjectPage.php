<?php

namespace App\Livewire;

use App\Contracts\ProjectBalanceSource;
use App\Data\ProjectRow;
use App\Services\Projects\ProjectAccess;
use App\Services\Projects\ProjectDetails;
use App\Services\Projects\ProjectDirectory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One project in four tabs (it replaces the Windows program's `projwin`, `allaccounts` and `lifetime` screens):
 * overview, accounts, budget lines and lifetime. Money is left out for roles without the finance permission.
 */
#[Layout('components.layouts.app')]
class ProjectPage extends Component
{
    public const TABS = ['overview', 'accounts', 'budget', 'lifetime'];

    public int $accountId;

    #[Url]
    public string $tab = 'overview';

    public function mount(int $account, ProjectDirectory $directory, ProjectAccess $access): void
    {
        abort_unless($access->canOpen(auth()->user(), $account), 403);
        abort_if($directory->find($account) === null, 404);

        $this->accountId = $account;
    }

    public function show(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    public function render(ProjectDirectory $directory, ProjectBalanceSource $balances, ProjectDetails $details, ProjectAccess $access): View
    {
        $showMoney = $access->seesMoney(auth()->user());
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'overview';

        /** @var ProjectRow $project */
        $project = $directory->find($this->accountId);

        return view('livewire.project-page', [
            'project' => $project,
            'tab' => $tab,
            'tabs' => self::TABS,
            'showMoney' => $showMoney,
            'balance' => $showMoney && in_array($tab, ['overview'], true) ? $balances->balancesFor([$this->accountId])->get($this->accountId) : null,
            'cashAccounts' => $showMoney && $tab === 'overview' ? $balances->byCashAccount($this->accountId) : collect(),
            'siblings' => $tab === 'accounts' ? $directory->siblings($project) : collect(),
            'siblingBalances' => $showMoney && $tab === 'accounts' ? $balances->balancesFor($directory->siblings($project)->pluck('accountId')->values()->all()) : collect(),
            'budgetLines' => $tab === 'budget' ? $details->budgetLines($this->accountId) : collect(),
            'lifetime' => $tab === 'lifetime' && $showMoney ? $details->lifetime($this->accountId) : collect(),
        ]);
    }
}
