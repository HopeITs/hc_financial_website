<?php

namespace App\Livewire;

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Models\Contract;
use App\Models\ContractAudit;
use App\Models\ContractProject;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Services\Contracts\BudgetAccountSuggester;
use App\Services\Contracts\BudgetSheetReader;
use App\Services\Contracts\ContractActuals;
use App\Services\Contracts\ContractAuditTrail;
use App\Services\Contracts\ContractBudgets;
use App\Services\Projects\ProjectAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * The budget of each project of one agreement, shown in the agreement's Projects tab: the budget lines of the project,
 * each on one expense account, in the agreement currency, with their total against the project's share. Lines are
 * added and removed while the agreement is open, also after approval: an agreement approved before its budget matched
 * its shares is matched here later. A whole budget can come from Excel: the file is read, an account is proposed for
 * each line (BudgetAccountSuggester), and the person checks the preview before every ticked line is added at once.
 */
class ContractBudget extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $contractId;

    public string $lineProject = '';

    public string $lineName = '';

    public string $lineAccount = '';

    public string $lineAmount = '';

    /** The donor's budget category of the new line (2026-11-26). */
    public string $lineCategory = '';

    public string $importProject = '';

    public ?TemporaryUploadedFile $budgetFile = null;

    /** @var list<array{include: bool, category: string, name: string, account: string, source: string, amount: string, note: string}> the read file, being checked */
    public array $importRows = [];

    /** @var list<string> the currencies of the amount column the file was read from */
    public array $importCurrencies = [];

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

        $budgets->add($contract, $data['lineProject'], $data['lineName'], $data['lineAccount'], $data['lineAmount'], $this->user(), 'form', $this->lineCategory);

        $this->reset('lineName', 'lineAccount', 'lineAmount', 'lineCategory');
        $this->say(__('contracts.budget_line_added'));
        $this->dispatch('budget-changed');
    }

    /**
     * Reads an Excel budget for one project and proposes an account for each line. Nothing is saved yet: the person
     * checks the preview, changes what is wrong, and then adds the whole budget with importBudget().
     */
    public function readBudgetFile(BudgetSheetReader $reader, BudgetAccountSuggester $suggester): void
    {
        $contract = $this->changeableContract();

        $data = $this->validate([
            'importProject' => ['required', Rule::in($contract->projects()->pluck('project_code')->all())],
            'budgetFile' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        try {
            $read = $reader->read($this->budgetFile->getRealPath(), $contract->currency);
        } catch (InvalidArgumentException $unreadable) {
            $this->addError('budgetFile', $unreadable->getMessage());

            return;
        } catch (Throwable) {
            $this->addError('budgetFile', __('contracts.import_unreadable'));

            return;
        }

        if ($read === []) {
            $this->addError('budgetFile', __('contracts.import_empty'));

            return;
        }

        $accounts = $this->accounts();
        $otherNames = $this->accounts(otherLanguage: true);
        $existing = GlBudgetLine::query()->where('project_code', $data['importProject'])->pluck('name')->map(fn ($name): string => mb_strtolower((string) $name))->all();
        $seen = [];
        $rows = [];

        foreach ($read as $line) {
            $name = mb_substr(trim(str_replace('|', '/', $line['name'])), 0, 200);
            $suggestion = $suggester->suggest($line['type'] ?: $line['category'], $name, $accounts, $otherNames);
            $taken = in_array(mb_strtolower($name), $existing, true) || in_array(mb_strtolower($name), $seen, true);
            $seen[] = mb_strtolower($name);

            $rows[] = [
                'include' => ! $taken,
                'category' => $line['category'],
                'name' => $name,
                'account' => (string) ($suggestion['account'] ?? ''),
                'source' => (string) ($suggestion['source'] ?? ''),
                'amount' => $line['budget'],
                'note' => $taken ? 'duplicate' : '',
            ];
        }

        $this->importRows = $rows;
        $this->importCurrencies = array_values(array_unique(array_column($read, 'currency')));
        $this->budgetFile = null;
        $this->problems = [];
        $this->notice = null;
    }

    /** Saves the checked preview: every ticked line, all at once. */
    public function importBudget(ContractBudgets $budgets): void
    {
        $contract = $this->changeableContract();
        abort_if($this->importRows === [], 422);

        $rules = ['importProject' => ['required', Rule::in($contract->projects()->pluck('project_code')->all())]];
        $accounts = array_map('strval', array_keys($this->accounts()));

        foreach ($this->importRows as $index => $row) {
            $this->importRows[$index]['name'] = trim((string) $row['name']);

            if (! ($row['include'] ?? false)) {
                continue;
            }

            $rules["importRows.$index.name"] = ['required', 'string', 'max:200', 'not_regex:/\|/'];
            $rules["importRows.$index.account"] = ['required', Rule::in($accounts)];
            $rules["importRows.$index.amount"] = ['required', 'numeric', 'gt:0', 'max:9999999999999'];
        }

        $data = $this->validate($rules, [], [
            'importRows.*.name' => __('contracts.budget_line'),
            'importRows.*.account' => __('contracts.budget_account'),
            'importRows.*.amount' => __('contracts.budget_amount'),
        ]);

        $chosen = array_values(array_filter($this->importRows, fn (array $row): bool => (bool) ($row['include'] ?? false)));
        if ($chosen === []) {
            $this->problems = [__('contracts.import_nothing_chosen')];

            return;
        }

        $names = array_map(fn (array $row): string => mb_strtolower($row['name']), $chosen);
        $keys = array_map(fn (array $row): string => ContractBudgets::key($data['importProject'], $row['name']), $chosen);
        $clashes = array_unique(array_merge(
            array_keys(array_filter(array_count_values($names), fn (int $count): bool => $count > 1)),
            GlBudgetLine::query()->whereIn('key', $keys)->pluck('name')->map(fn ($name): string => mb_strtolower((string) $name))->all(),
        ));

        if ($clashes !== []) {
            $this->problems = [__('contracts.import_duplicates', ['names' => implode('، ', $clashes)])];
            $this->notice = null;

            return;
        }

        $count = $budgets->addMany($contract, $data['importProject'], array_map(fn (array $row): array => [
            'name' => $row['name'], 'account' => (string) $row['account'], 'amount' => (string) $row['amount'], 'category' => (string) ($row['category'] ?? ''),
        ], $chosen), $this->user());

        $this->cancelImport();
        $this->say(trans_choice('contracts.import_done', $count, ['count' => $count]));
        $this->dispatch('budget-changed');
    }

    /** Sets a line's budget category: it only groups lines in the donor's report, so a used line takes it too. */
    public function setCategory(string $key, string $category, ContractBudgets $budgets): void
    {
        $contract = $this->changeableContract();
        $line = GlBudgetLine::query()->whereKey($key)->whereIn('project_code', $contract->projects()->pluck('project_code'))->firstOrFail();
        $budgets->setCategory($line, $category);
        $this->say(__('contracts.budget_category_saved'));
    }

    public function cancelImport(): void
    {
        $this->reset('importRows', 'importCurrencies', 'budgetFile');
        $this->resetValidation();
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

        $budgets->remove($contract, $line, $this->user());
        $this->say(__('contracts.budget_line_removed'));
        $this->dispatch('budget-changed');
    }

    public function render(ContractActuals $actuals, ContractAuditTrail $trail): View
    {
        $contract = $this->contract()->load('projects.project');
        $codes = $contract->projects->pluck('project_code')->all();
        $lines = GlBudgetLine::query()->with('account')->whereIn('project_code', $codes)->orderBy('project_code')->orderBy('name')->get()->groupBy('project_code');
        $spent = $actuals->spentByLine($contract, $lines->flatten()->pluck('key')->all());
        $received = $actuals->receivedByProject($contract);
        $sum = fn (array $amounts): string => array_reduce($amounts, fn (string $total, mixed $amount): string => bcadd($total, (string) $amount, 2), '0.00');

        $sections = $contract->projects->map(function (ContractProject $share) use ($lines, $spent, $received, $sum): array {
            $projectLines = $lines->get($share->project_code, collect());
            $total = $sum($projectLines->pluck('budget')->all());
            $projectSpent = $sum($projectLines->map(fn (GlBudgetLine $line): string => $spent[$line->key] ?? '0.00')->all());

            return [
                'share' => $share,
                'lines' => $projectLines,
                'total' => $total,
                'balanced' => bccomp($total, (string) $share->amount, 2) === 0,
                'spent' => $projectSpent,
                'remaining' => bcsub($total, $projectSpent, 2),
                'received' => $received[$share->project_code] ?? '0.00',
            ];
        });

        $chosen = array_filter($this->importRows, fn (array $row): bool => (bool) ($row['include'] ?? false));
        $importTotal = $sum(array_map(fn (array $row): string => is_numeric($row['amount'] ?? null) ? (string) $row['amount'] : '0', $chosen));
        $importShare = (string) ($contract->projects->firstWhere('project_code', $this->importProject)?->amount ?? '0');
        $history = $trail->latest($contract, actions: ContractAudit::BUDGET_ACTIONS);

        return view('livewire.contract-budget', [
            'contract' => $contract,
            'sections' => $sections,
            'importTotal' => $importTotal,
            'importShare' => $importShare,
            'importMatches' => bccomp($importTotal, $importShare, 2) === 0,
            'importOtherCurrency' => array_values(array_diff($this->importCurrencies, [$contract->currency])),
            'importUnassigned' => count(array_filter($chosen, fn (array $row): bool => ($row['account'] ?? '') === '')),
            'spent' => $spent,
            'accounts' => $this->accounts(),
            'canChange' => Gate::allows('screen', ['contracts', 'edit']) && $contract->isOpen(),
            'hasCategories' => app(ContractBudgets::class)->hasCategories(),
            'needsMatching' => $contract->isApproved() && $sections->contains(fn (array $section): bool => ! $section['balanced']),
            'history' => $history,
            'historyPeople' => User::query()->whereIn('ID', $history->pluck('changed_by')->unique()->all())->get()->mapWithKeys(fn (User $person): array => [(int) $person->getKey() => $person->username])->all(),
        ]);
    }

    /** @return array<string, string> the active expense posting accounts, code => "code · name" (in the other language on request) */
    private function accounts(bool $otherLanguage = false): array
    {
        $arabic = (app()->getLocale() === 'ar') !== $otherLanguage;

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
