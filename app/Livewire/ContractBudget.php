<?php

namespace App\Livewire;

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Services\Contracts\ContractBudgets;
use App\Services\Projects\ProjectAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The budget of each project of one agreement, shown in the agreement's Projects tab: the budget lines of the project,
 * each on one expense account, in the agreement currency, with their total against the project's share. Lines are
 * added and removed while the agreement is open, also after approval: an agreement approved before its budget matched
 * its shares is matched here later.
 */
class ContractBudget extends Component
{
    #[Locked]
    public int $contractId;

    public string $lineProject = '';

    public string $lineName = '';

    public string $lineAccount = '';

    public string $lineAmount = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(int $contractId, ProjectAccess $access): void
    {
        Gate::authorize('screen', ['contracts', 'view']);
        abort_unless(Contract::query()->whereKey($contractId)->visibleTo($access->limitedTo($this->user()))->exists(), 404);

        $this->contractId = $contractId;
    }

    public function addLine(ContractBudgets $budgets): void
    {
        $contract = $this->changeableContract();
        $this->lineName = trim($this->lineName);

        $data = $this->validate([
            'lineProject' => ['required', Rule::in($contract->projects()->pluck('project_code')->all())],
            'lineName' => ['required', 'string', 'max:200', 'not_regex:/\|/'],
            'lineAccount' => ['required', Rule::in(array_keys($this->accounts()))],
            'lineAmount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ]);

        if (GlBudgetLine::query()->whereKey(ContractBudgets::key($data['lineProject'], $data['lineName']))->exists()) {
            $this->addError('lineName', __('contracts.budget_line_exists'));

            return;
        }

        $budgets->add($contract, $data['lineProject'], $data['lineName'], $data['lineAccount'], $data['lineAmount']);

        $this->reset('lineName', 'lineAccount', 'lineAmount');
        $this->say(__('contracts.budget_line_added'));
        $this->dispatch('budget-changed');
    }

    public function removeLine(string $key, ContractBudgets $budgets): void
    {
        $contract = $this->changeableContract();
        $line = GlBudgetLine::query()->whereKey($key)->whereIn('project_code', $contract->projects()->pluck('project_code'))->firstOrFail();

        if ($budgets->isUsed($line)) {
            $this->problems = [__('contracts.budget_line_used')];
            $this->notice = null;

            return;
        }

        $line->delete();
        $this->say(__('contracts.budget_line_removed'));
        $this->dispatch('budget-changed');
    }

    public function render(): View
    {
        $contract = $this->contract()->load('projects.project');
        $codes = $contract->projects->pluck('project_code')->all();
        $lines = GlBudgetLine::query()->with('account')->whereIn('project_code', $codes)->orderBy('project_code')->orderBy('name')->get()->groupBy('project_code');

        $sections = $contract->projects->map(function (ContractProject $share) use ($lines): array {
            $projectLines = $lines->get($share->project_code, collect());
            $total = array_reduce($projectLines->pluck('budget')->all(), fn (string $sum, mixed $amount): string => bcadd($sum, (string) $amount, 2), '0.00');

            return [
                'share' => $share,
                'lines' => $projectLines,
                'total' => $total,
                'balanced' => bccomp($total, (string) $share->amount, 2) === 0,
            ];
        });

        return view('livewire.contract-budget', [
            'contract' => $contract,
            'sections' => $sections,
            'accounts' => $this->accounts(),
            'canChange' => Gate::allows('screen', ['contracts', 'edit']) && $contract->isOpen(),
            'needsMatching' => $contract->isApproved() && $sections->contains(fn (array $section): bool => ! $section['balanced']),
        ]);
    }

    /** @return array<string, string> the active expense posting accounts, code => "code · name" */
    private function accounts(): array
    {
        $arabic = app()->getLocale() === 'ar';

        return GlAccount::query()
            ->where('class', AccountClass::Expenses->value)->where('level', AccountLevel::Posting->value)->where('is_active', true)
            ->orderBy('code')->get(['code', 'name_en', 'name_ar'])
            ->mapWithKeys(fn (GlAccount $account): array => [$account->code => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en)])
            ->all();
    }

    /** The agreement, while its budget may change: by the preparing roles, until the agreement is ended or cancelled. */
    private function changeableContract(): Contract
    {
        Gate::authorize('screen', ['contracts', 'edit']);
        $contract = $this->contract();
        abort_unless($contract->isOpen(), 403);

        return $contract;
    }

    private function contract(): Contract
    {
        return Contract::query()->findOrFail($this->contractId);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function say(string $message): void
    {
        $this->problems = [];
        $this->notice = $message;
    }
}
