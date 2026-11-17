<?php

namespace App\Livewire;

use App\Enums\ContractClosingOutcome;
use App\Enums\ContractReportKind;
use App\Enums\ContractReportStatus;
use App\Enums\ContractRestriction;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\ContractReport;
use App\Models\GlCity;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Contracts\ContractBudgets;
use App\Services\Contracts\ContractException;
use App\Services\Contracts\ContractFiles;
use App\Services\Contracts\ContractProjects;
use App\Services\Contracts\ContractWorkflow;
use App\Services\Ledger\Currencies;
use App\Services\Projects\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * One agreement (finance design of 2026-11-16), in tabs: its details, its split over projects (by amount, adding
 * up to the agreement; a new project is created here too), its expected instalments (some conditional), the reports owed to the donor, and its closing.
 * The budget per contract and the link to receipts and payments come in the next step.
 */
#[Layout('components.layouts.app')]
class ContractPage extends Component
{
    use WithFileUploads;

    private const TABS = ['details', 'projects', 'instalments', 'reports', 'closing'];

    #[Locked]
    public int $contractId;

    #[Url]
    public string $tab = 'details';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public bool $editingDetails = false;

    public string $title = '';

    public string $donorReference = '';

    public string $signedOn = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $amount = '';

    public string $fixedRate = '';

    public string $restriction = '';

    public string $status = '';

    public string $notes = '';

    public ?TemporaryUploadedFile $signedFile = null;

    public string $shareProject = '';

    public string $shareAmount = '';

    public string $newProjectCode = '';

    public string $newProjectName = '';

    public string $newProjectCity = '';

    public string $newProjectAmount = '';

    public string $instalmentDue = '';

    public string $instalmentAmount = '';

    public string $instalmentCondition = '';

    public bool $instalmentConditional = false;

    public string $reportKind = 'both';

    public string $reportFrom = '';

    public string $reportTo = '';

    public string $reportDue = '';

    public string $reportInstalment = '';

    public ?TemporaryUploadedFile $reportFile = null;

    public string $closingOutcome = '';

    public string $closingNote = '';

    public ?TemporaryUploadedFile $closingFile = null;

    public function mount(Contract $contract, ProjectAccess $access): void
    {
        $user = $this->user();
        abort_unless(Contract::query()->whereKey($contract->id)->visibleTo($access->limitedTo($user))->exists(), 404);

        $this->contractId = $contract->id;

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'details';
        }
    }

    public function editDetails(): void
    {
        $this->authorizeChange();

        $contract = $this->contract();
        $this->resetValidation();
        $this->title = $contract->title;
        $this->donorReference = (string) $contract->donor_reference;
        $this->signedOn = (string) $contract->signed_on?->format('Y-m-d');
        $this->startsOn = $contract->starts_on->format('Y-m-d');
        $this->endsOn = $contract->ends_on->format('Y-m-d');
        $this->amount = (string) $contract->amount;
        $this->fixedRate = (string) $contract->fixed_rate;
        $this->restriction = $contract->restriction->value;
        $this->status = $contract->status->value;
        $this->notes = (string) $contract->notes;
        $this->signedFile = null;
        $this->editingDetails = true;
    }

    public function cancelDetails(): void
    {
        $this->resetValidation();
        $this->editingDetails = false;
    }

    public function saveDetails(ContractFiles $files): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        $locked = $contract->isApproved();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'donorReference' => ['nullable', 'string', 'max:255'],
            'signedOn' => ['nullable', 'date'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'amount' => $locked ? [] : ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'fixedRate' => $locked ? [] : ['required', 'numeric', 'gt:0'],
            'restriction' => ['required', Rule::enum(ContractRestriction::class)],
            'status' => ['required', Rule::in([ContractStatus::Draft->value, ContractStatus::Signed->value, ContractStatus::Running->value, ContractStatus::Cancelled->value])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signedFile' => ['nullable', ContractFiles::RULE],
        ]);

        $attributes = [
            'title' => trim($data['title']),
            'donor_reference' => trim((string) $data['donorReference']) ?: null,
            'signed_on' => $data['signedOn'] ?: null,
            'starts_on' => $data['startsOn'],
            'ends_on' => $data['endsOn'],
            'restriction' => $data['restriction'],
            'status' => $data['status'],
            'notes' => trim((string) $data['notes']) ?: null,
        ];

        if (! $locked) {
            $attributes['amount'] = $data['amount'];
            $attributes['fixed_rate'] = $contract->currency === 'USD' ? '1' : $data['fixedRate'];
        }

        if ($this->signedFile !== null) {
            $stored = $files->store($contract->id, $this->signedFile);
            $files->forget($contract->signed_file_path);
            $attributes['signed_file_path'] = $stored['path'];
            $attributes['signed_file_name'] = $stored['name'];
        }

        $contract->update($attributes);

        $this->signedFile = null;
        $this->editingDetails = false;
        $this->say(__('contracts.saved'));
    }

    public function approve(ContractWorkflow $workflow): void
    {
        Gate::authorize('screen', ['contracts', 'approve']);

        try {
            $workflow->approve($this->contract(), $this->user());
            $this->say(__('contracts.approved'));
        } catch (ContractException $refused) {
            $this->problems = $refused->problems;
            $this->notice = null;
        }
    }

    public function addShare(): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        if ($this->isLocked($contract)) {
            return;
        }

        $data = $this->validate([
            'shareProject' => ['required', Rule::exists('gl_projects', 'code'), Rule::unique('gl_contract_projects', 'project_code')->where('contract_id', $contract->id)],
            'shareAmount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ]);

        ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => $data['shareProject'], 'amount' => $data['shareAmount']]);

        $this->reset('shareProject', 'shareAmount');
        $this->say(__('contracts.share_added'));
    }

    /** A new ledger project created inside this agreement, with its share of the agreement. */
    public function createProject(ContractProjects $projects): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        if ($this->isLocked($contract)) {
            return;
        }

        $this->newProjectCode = ContractProjects::normalCode($this->newProjectCode);

        $data = $this->validate([
            'newProjectCode' => ['required', 'string', 'max:16', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('gl_projects', 'code')],
            'newProjectName' => ['required', 'string', 'max:255'],
            'newProjectCity' => ['nullable', Rule::in(GlCity::activeNames())],
            'newProjectAmount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ]);

        $projects->create($contract, [
            'code' => $data['newProjectCode'],
            'name' => $data['newProjectName'],
            'city' => $data['newProjectCity'] ?? null,
            'amount' => $data['newProjectAmount'],
        ]);

        $this->reset('newProjectCode', 'newProjectName', 'newProjectCity', 'newProjectAmount');
        $this->say(__('contracts.project_created'));
    }

    /** The Budget section changed a line: re-render, so the budget warning and the "needs matching" mark follow. */
    #[On('budget-changed')]
    public function refreshBudget(): void {}

    public function removeShare(int $id): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        if ($this->isLocked($contract)) {
            return;
        }

        ContractProject::query()->where('contract_id', $contract->id)->whereKey($id)->delete();
        $this->say(__('contracts.share_removed'));
    }

    public function addInstalment(): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        if ($this->isLocked($contract)) {
            return;
        }

        $data = $this->validate([
            'instalmentDue' => ['required', 'date'],
            'instalmentAmount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'instalmentCondition' => [$this->instalmentConditional ? 'required' : 'nullable', 'string', 'max:255'],
            'instalmentConditional' => ['boolean'],
        ]);

        ContractInstalment::query()->create([
            'contract_id' => $contract->id,
            'number' => (int) $contract->instalments()->max('number') + 1,
            'due_on' => $data['instalmentDue'],
            'amount' => $data['instalmentAmount'],
            'condition' => trim((string) $data['instalmentCondition']) ?: null,
            'is_conditional' => $data['instalmentConditional'],
        ]);

        $this->reset('instalmentDue', 'instalmentAmount', 'instalmentCondition', 'instalmentConditional');
        $this->say(__('contracts.instalment_added'));
    }

    public function removeInstalment(int $id): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        if ($this->isLocked($contract)) {
            return;
        }

        ContractInstalment::query()->where('contract_id', $contract->id)->whereKey($id)->delete();
        $this->say(__('contracts.instalment_removed'));
    }

    /** The condition of a conditional instalment is met today: from now on it counts as income. */
    public function markConditionMet(int $id): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        abort_unless($contract->isApproved() && $contract->isOpen(), 403);

        $instalment = ContractInstalment::query()->where('contract_id', $contract->id)->whereKey($id)->firstOrFail();

        if ($instalment->is_conditional && $instalment->condition_met_on === null) {
            $instalment->update(['condition_met_on' => CarbonImmutable::today()->toDateString()]);
            $this->say(__('contracts.condition_met', ['number' => $instalment->number]));
        }
    }

    public function addReport(ContractFiles $files): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        abort_unless($contract->isOpen(), 403);

        $data = $this->validate([
            'reportKind' => ['required', Rule::enum(ContractReportKind::class)],
            'reportFrom' => ['required', 'date'],
            'reportTo' => ['required', 'date', 'after_or_equal:reportFrom'],
            'reportDue' => ['required', 'date'],
            'reportInstalment' => ['nullable', Rule::exists('gl_contract_instalments', 'id')->where('contract_id', $contract->id)],
            'reportFile' => ['nullable', ContractFiles::RULE],
        ]);

        $stored = $this->reportFile !== null ? $files->store($contract->id, $this->reportFile) : null;

        ContractReport::query()->create([
            'contract_id' => $contract->id,
            'kind' => $data['reportKind'],
            'period_from' => $data['reportFrom'],
            'period_to' => $data['reportTo'],
            'due_on' => $data['reportDue'],
            'status' => $stored !== null ? ContractReportStatus::Sent->value : ContractReportStatus::NotSent->value,
            'sent_on' => $stored !== null ? CarbonImmutable::today()->toDateString() : null,
            'file_path' => $stored['path'] ?? null,
            'file_name' => $stored['name'] ?? null,
            'instalment_id' => $data['reportInstalment'] !== '' ? (int) $data['reportInstalment'] : null,
        ]);

        $this->reset('reportFrom', 'reportTo', 'reportDue', 'reportInstalment', 'reportFile');
        $this->reportKind = ContractReportKind::Both->value;
        $this->say(__('contracts.report_added'));
    }

    public function setReportStatus(int $id, string $status): void
    {
        $this->authorizeChange();
        $contract = $this->contract();
        $next = ContractReportStatus::tryFrom($status);
        abort_if($next === null, 422);

        $report = ContractReport::query()->where('contract_id', $contract->id)->whereKey($id)->firstOrFail();
        $report->update([
            'status' => $next->value,
            'sent_on' => $next === ContractReportStatus::NotSent ? null : ($report->sent_on?->toDateString() ?? CarbonImmutable::today()->toDateString()),
        ]);

        $this->say(__('contracts.report_saved'));
    }

    public function removeReport(int $id, ContractFiles $files): void
    {
        $this->authorizeChange();
        $contract = $this->contract();

        $report = ContractReport::query()->where('contract_id', $contract->id)->whereKey($id)->firstOrFail();
        $files->forget($report->file_path);
        $report->delete();

        $this->say(__('contracts.report_removed'));
    }

    public function requestClosing(ContractWorkflow $workflow, ContractFiles $files): void
    {
        $this->authorizeChange();
        $contract = $this->contract();

        $data = $this->validate([
            'closingOutcome' => ['required', Rule::enum(ContractClosingOutcome::class)],
            'closingNote' => ['required', 'string', 'max:2000'],
            'closingFile' => ['required', ContractFiles::RULE],
        ]);

        try {
            $stored = $files->store($contract->id, $this->closingFile);
            $workflow->requestClosing($contract, ContractClosingOutcome::from($data['closingOutcome']), trim($data['closingNote']), $stored['path'], $stored['name'], $this->user());
        } catch (ContractException $refused) {
            $files->forget($stored['path'] ?? null);
            $this->problems = $refused->problems;

            return;
        }

        $this->reset('closingOutcome', 'closingNote', 'closingFile');
        $this->say(__('contracts.closing_sent'));
    }

    public function approveClosing(ContractWorkflow $workflow): void
    {
        Gate::authorize('screen', ['contracts', 'approve']);

        try {
            $workflow->approveClosing($this->contract(), $this->user());
            $this->say(__('contracts.closing_approved'));
        } catch (ContractException $refused) {
            $this->problems = $refused->problems;
        }
    }

    public function render(Currencies $currencies, ContractWorkflow $workflow, ContractBudgets $budgets): View
    {
        $contract = $this->contract()->load(['donor', 'projects.project', 'instalments', 'reports.instalment']);
        $budgetWarnings = array_map(fn (array $row): string => __('contracts.budget_mismatch', $row), $budgets->mismatches($contract));
        $today = CarbonImmutable::today();
        $toUsd = fn (string $amount): string => $currencies->toUsd($contract->currency, $amount, (string) $contract->fixed_rate);

        $sharesTotal = $this->sum($contract->projects->pluck('amount')->all());
        $instalmentsTotal = $this->sum($contract->instalments->pluck('amount')->all());
        $recognised = $this->sum($contract->instalments->filter->isRecognised()->pluck('amount')->all());
        $late = $contract->instalments->filter(fn (ContractInstalment $instalment) => $instalment->daysLate($today) > 0 && ! $instalment->isRecognised());
        $nextReport = $contract->reports->first(fn (ContractReport $report) => $report->status !== ContractReportStatus::Accepted && $report->due_on->gte($today));
        $user = $this->user();
        $canChange = Gate::allows('screen', ['contracts', 'edit']) && $contract->isOpen();

        return view('livewire.contract-page', [
            'contract' => $contract,
            'tabs' => array_map(fn (string $key) => ['key' => $key, 'label' => __('contracts.tab_'.$key)], self::TABS),
            'today' => $today,
            'sharesTotal' => $sharesTotal,
            'sharesBalanced' => bccomp($sharesTotal, (string) $contract->amount, 2) === 0,
            'instalmentsTotal' => $instalmentsTotal,
            'instalmentsBalanced' => bccomp($instalmentsTotal, (string) $contract->amount, 2) === 0,
            'recognised' => $recognised,
            'lateCount' => $late->count(),
            'nextReport' => $nextReport,
            'kpi' => [
                'value' => number_format((float) $contract->amount, 2),
                'value_usd' => number_format((float) $toUsd((string) $contract->amount), 2),
                'income' => number_format((float) $recognised, 2),
            ],
            'shareUsd' => $contract->projects->mapWithKeys(fn (ContractProject $share) => [$share->id => $toUsd((string) $share->amount)]),
            'sharePercent' => $contract->projects->mapWithKeys(fn (ContractProject $share) => [$share->id => bccomp((string) $contract->amount, '0', 2) === 1
                ? bcdiv(bcmul((string) $share->amount, '100', 6), (string) $contract->amount, 2)
                : '0.00']),
            'projects' => GlProject::query()->whereNotIn('code', $contract->projects->pluck('project_code'))->orderBy('code')->get(['code', 'name']),
            'cities' => GlCity::query()->where('is_active', true)->orderBy('name')->get(),
            'canChange' => $canChange,
            'canChangeMoney' => $canChange && ! $contract->isApproved(),
            'canApprove' => $contract->isOpen() && ! $contract->isApproved() && Gate::allows('screen', ['contracts', 'approve']),
            'approvalProblems' => $contract->isApproved() ? [] : $workflow->problemsBeforeApproval($contract),
            'budgetWarnings' => $budgetWarnings,
            'needsMatching' => $contract->isApproved() && $contract->isOpen() && $budgetWarnings !== [],
            'canRequestClosing' => $canChange && $contract->isApproved() && ! $contract->isClosingRequested(),
            'nextClosingStep' => $workflow->nextClosingStep($contract),
            'canApproveClosing' => Gate::allows('screen', ['contracts', 'approve']) && $workflow->canApproveClosing($contract, $user),
            'restrictions' => ContractRestriction::cases(),
            'statuses' => [ContractStatus::Draft, ContractStatus::Signed, ContractStatus::Running, ContractStatus::Cancelled],
            'reportKinds' => ContractReportKind::cases(),
            'reportStatuses' => ContractReportStatus::cases(),
            'outcomes' => ContractClosingOutcome::cases(),
            'reminderDays' => ContractReport::REMINDER_DAYS,
            'people' => User::query()->whereIn('ID', array_filter([$contract->created_by, $contract->approved_by, $contract->closing_requested_by, $contract->closing_fm_by, $contract->closing_gm_by]))->get()->mapWithKeys(fn (User $person) => [$person->getKey() => $person->username]),
        ]);
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

    private function authorizeChange(): void
    {
        Gate::authorize('screen', ['contracts', 'edit']);
        abort_unless($this->contract()->isOpen(), 403);
    }

    /** Once approved, the project shares and the instalments are fixed: says so and returns true. */
    private function isLocked(Contract $contract): bool
    {
        if (! $contract->isApproved()) {
            return false;
        }

        $this->problems = [__('contracts.problem_locked')];
        $this->notice = null;

        return true;
    }

    private function say(string $message): void
    {
        $this->problems = [];
        $this->notice = $message;
    }

    /**
     * @param  array<int, mixed>  $amounts
     * @return numeric-string
     */
    private function sum(array $amounts): string
    {
        return array_reduce($amounts, fn (string $total, mixed $amount): string => bcadd($total, (string) $amount, 2), '0.00');
    }
}
