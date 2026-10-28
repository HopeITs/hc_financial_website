<?php

namespace App\Livewire;

use App\Enums\RequestCategory;
use App\Enums\RequestStatus;
use App\Enums\ScreenAction;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Projects\ProjectAccess;
use App\Services\Requests\RequestException;
use App\Services\Requests\RequestWorkflow;
use App\Services\Requests\SubmissionDeadline;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * The project managers' requests: a list, one request with its lines, quotes, approvals and history, and the form a
 * manager uses to prepare one. The manager edits until a decision is made; the Financial Manager decides first and, above
 * USD 2,000, the General Manager after (see RequestWorkflow). A manager sees the requests of their own projects only.
 */
#[Layout('components.layouts.app')]
class Requests extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const PER_PAGE = 25;

    private const BLANK_LINE = ['budget_line' => '', 'amount' => '', 'currency' => '', 'rate' => '', 'note' => ''];

    #[Url(as: 'request')]
    public ?int $requestId = null;

    #[Url]
    public string $status = '';

    #[Url]
    public string $project = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fProject = '';

    public string $fType = LedgerRequest::MONTHLY;

    public string $fMonth = '';

    public string $fCategory = '';

    public string $fDescription = '';

    /** @var array<int, array<string, string>> */
    public array $fLines = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $quoteFiles = [];

    public bool $showReject = false;

    public string $rejectReason = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $failures = [];

    public function mount(): void
    {
        $flash = session('notice');
        $this->notice = is_string($flash) ? $flash : null;
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['status', 'project'], true)) {
            $this->resetPage();
        }
    }

    public function newRequest(): void
    {
        $this->resetFeedback();
        $this->editingId = null;
        $this->fProject = $this->project !== '' ? $this->project : (string) array_key_first($this->projectChoices());
        $this->fType = LedgerRequest::MONTHLY;
        $this->fMonth = now()->format('Y-m');
        $this->fCategory = '';
        $this->fDescription = '';
        $this->fLines = [self::BLANK_LINE];
        $this->showForm = true;
        $this->requestId = null;
    }

    public function editRequest(RequestWorkflow $workflow): void
    {
        $this->resetFeedback();
        $request = $this->current();
        Gate::authorize('update', $request);

        if (! $workflow->isEditable($request)) {
            $this->failures = [__('requests.problem.locked')];

            return;
        }

        $this->editingId = $request->id;
        $this->fProject = $request->project_code;
        $this->fType = $request->type === LedgerRequest::EXTRA ? LedgerRequest::EXTRA : LedgerRequest::MONTHLY;
        $this->fMonth = $request->month?->format('Y-m') ?? now()->format('Y-m');
        $this->fCategory = (string) $request->category;
        $this->fDescription = (string) $request->description;
        $this->fLines = $request->lines->map(fn ($line) => [
            'budget_line' => $line->budget_line_key, 'amount' => (string) $line->amount, 'currency' => $line->currency,
            'rate' => $line->currency === 'SYP' ? rtrim(rtrim((string) $line->rate, '0'), '.') : '', 'note' => (string) $line->note,
        ])->values()->all();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetErrorBag();
    }

    public function addLine(): void
    {
        $this->fLines[] = self::BLANK_LINE;
    }

    public function removeLine(int $index): void
    {
        unset($this->fLines[$index]);
        $this->fLines = array_values($this->fLines);

        if ($this->fLines === []) {
            $this->fLines = [self::BLANK_LINE];
        }
    }

    /** A new project means new budget lines, so the lines are cleared. */
    public function updatedFProject(): void
    {
        $this->fLines = [self::BLANK_LINE];
    }

    /** Choosing a budget line fills the currency the line is budgeted in. */
    public function updatedFLines(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, '');

        if ($field === 'budget_line' && isset($this->fLines[(int) $index])) {
            $currency = (string) GlBudgetLine::query()->whereKey($value)->value('currency');
            $this->fLines[(int) $index]['currency'] = in_array($currency, ['USD', 'SYP'], true) ? $currency : 'USD';
        }
    }

    public function save(RequestWorkflow $workflow): void
    {
        $this->resetFeedback();
        $header = [
            'project' => $this->fProject, 'type' => $this->fType, 'month' => $this->fMonth, 'category' => $this->fCategory,
            'description' => $this->fDescription,
        ];

        try {
            if ($this->editingId === null) {
                $request = $workflow->create($this->user(), $header, array_values($this->fLines));
            } else {
                $request = $workflow->update($this->user(), $this->find($this->editingId), $header, array_values($this->fLines));
            }
        } catch (RequestException $e) {
            $this->failures = $e->problems;

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->showForm = false;
        $this->requestId = $request->id;
        $this->notice = __('requests.saved_notice', ['number' => $request->id]);
    }

    public function submit(RequestWorkflow $workflow): void
    {
        $this->act(fn (User $user, LedgerRequest $request) => $workflow->submit($user, $request), 'submitted_notice');
    }

    /** A monthly request submitted after its deadline (R4-10): still accepted, but the manager is told. */
    public function lateWarning(LedgerRequest $request, SubmissionDeadline $deadline): ?string
    {
        if ($request->type !== LedgerRequest::MONTHLY || $request->month === null || $request->submitted_at === null || ! $deadline->isLate($request->month, $request->submitted_at)) {
            return null;
        }

        return __('requests.late_warning', ['month' => $request->month->format('Y-m'), 'due' => $deadline->dueBy($request->month)->toDateString()]);
    }

    public function approve(RequestWorkflow $workflow): void
    {
        $this->act(fn (User $user, LedgerRequest $request) => $workflow->approve($user, $request), 'approved_notice');
    }

    public function close(RequestWorkflow $workflow): void
    {
        $this->act(fn (User $user, LedgerRequest $request) => $workflow->close($user, $request), 'closed_notice');
    }

    public function openReject(): void
    {
        $this->resetFeedback();
        Gate::authorize('decide', $this->current());
        $this->rejectReason = '';
        $this->showReject = true;
    }

    public function closeReject(): void
    {
        $this->showReject = false;
    }

    public function reject(RequestWorkflow $workflow): void
    {
        $reason = $this->rejectReason;

        if (trim($reason) === '') {
            $this->addError('rejectReason', __('requests.problem.reason_needed'));

            return;
        }

        $this->act(fn (User $user, LedgerRequest $request) => $workflow->reject($user, $request, $reason), 'rejected_notice');
        $this->showReject = false;
    }

    public function updatedQuoteFiles(RequestWorkflow $workflow): void
    {
        $this->resetFeedback();
        $this->validate(
            ['quoteFiles' => ['array', 'max:'.RequestCategory::MAX_QUOTES], 'quoteFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
            ['quoteFiles.max' => __('requests.problem.quotes_full', ['max' => RequestCategory::MAX_QUOTES]), 'quoteFiles.*.*' => __('requests.problem.file_type')],
        );

        $request = $this->current();
        $files = $this->quoteFiles;
        $this->quoteFiles = [];

        try {
            foreach ($files as $file) {
                $workflow->addQuote($this->user(), $request, $file);
            }
        } catch (RequestException $e) {
            $this->failures = $e->problems;
        } catch (AuthorizationException) {
            abort(403);
        }
    }

    public function removeQuote(RequestWorkflow $workflow, int $quoteId): void
    {
        $this->resetFeedback();

        try {
            $workflow->removeQuote($this->user(), $this->current(), $quoteId);
        } catch (RequestException $e) {
            $this->failures = $e->problems;
        } catch (AuthorizationException) {
            abort(403);
        }
    }

    public function render(RequestWorkflow $workflow): View
    {
        $user = $this->user();

        if ($this->showForm) {
            return view('livewire.requests', ['mode' => 'form', 'projects' => $this->projectChoices(), 'budgetLines' => $this->budgetLinesOf($this->fProject)]
                + ['categories' => RequestCategory::cases(), 'current' => null]);
        }

        if ($this->requestId !== null) {
            $request = $this->visible()->with(['lines.budgetLine', 'approvals', 'audits', 'quotes', 'project'])->find($this->requestId);
            abort_if($request === null, 404);
            Gate::authorize('view', $request);

            return view('livewire.requests', ['mode' => 'detail', 'current' => $request] + $this->detail($request, $user, $workflow));
        }

        return view('livewire.requests', ['mode' => 'list', 'current' => null] + $this->listing());
    }

    /** @return array<string, mixed> */
    private function detail(LedgerRequest $request, User $user, RequestWorkflow $workflow): array
    {
        $editable = $workflow->isEditable($request);
        $pending = $workflow->pendingRole($request);
        $ids = $request->audits->pluck('changed_by')->merge($request->approvals->pluck('decided_by'))->push($request->requested_by)->unique()->values();

        return [
            'names' => $this->names($ids),
            'canEdit' => $editable && Gate::allows('update', $request),
            'canDecide' => $request->state() === RequestStatus::Submitted && Gate::allows('decide', $request),
            'canClose' => $request->state() === RequestStatus::Approved && Gate::allows('close', $request),
            'pendingRole' => $pending,
            'warnings' => $workflow->budgetWarnings($request),
            'lateWarning' => $this->lateWarning($request, app(SubmissionDeadline::class)),
            'vouchers' => Voucher::query()->where('request_id', $request->id)->orderBy('id')->get(['id', 'type', 'date', 'status']),
            'ownRequest' => $request->requested_by === (int) $user->getAuthIdentifier(),
            'quotesNeeded' => $request->category !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function listing(): array
    {
        $list = $this->visible()
            ->when(RequestStatus::tryFrom($this->status) !== null, fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->project !== '', fn (Builder $query) => $query->where('project_code', $this->project))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return [
            'list' => $list,
            'names' => $this->names($list->getCollection()->pluck('requested_by')->unique()->values()),
            'statuses' => RequestStatus::cases(),
            'projects' => $this->projectChoices(),
            'canCreate' => $this->user()->canOnScreen('requests', ScreenAction::Create),
        ];
    }

    /** @return Builder<LedgerRequest> */
    private function visible(): Builder
    {
        $limit = app(ProjectAccess::class)->limitedTo($this->user());

        return LedgerRequest::query()->when($limit !== null, fn (Builder $query) => $query->whereIn(
            'project_code',
            GlProject::query()->whereIn('legacy_account_id', $limit ?? [])->select('code'),
        ));
    }

    /** @return array<string, string> project code => "CODE · name" */
    private function projectChoices(): array
    {
        $limit = app(ProjectAccess::class)->limitedTo($this->user());

        $choices = [];

        foreach (GlProject::query()->when($limit !== null, fn (Builder $query) => $query->whereIn('legacy_account_id', $limit ?? []))->orderBy('code')->get(['code', 'name']) as $project) {
            $choices[$project->code] = $project->code.' · '.$project->name;
        }

        return $choices;
    }

    /** @return Collection<int, GlBudgetLine> */
    private function budgetLinesOf(string $projectCode): Collection
    {
        return $projectCode === '' ? new Collection : GlBudgetLine::query()->where('project_code', $projectCode)->orderBy('key')->get();
    }

    /** The open request, loaded through the visibility scope so a foreign one is a 404. */
    private function current(): LedgerRequest
    {
        abort_if($this->requestId === null, 404);

        return $this->find($this->requestId);
    }

    private function find(int $id): LedgerRequest
    {
        $request = $this->visible()->find($id);
        abort_if($request === null, 404);

        return $request;
    }

    /** @param  callable(User, LedgerRequest): mixed  $action */
    private function act(callable $action, string $notice): void
    {
        $this->resetFeedback();
        $request = $this->current();

        try {
            $action($this->user(), $request);
            $this->notice = __('requests.'.$notice, ['number' => $request->id]);
        } catch (RequestException $e) {
            $this->failures = $e->problems;
        } catch (AuthorizationException) {
            abort(403);
        }
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failures = [];
        $this->resetErrorBag();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * The names of legacy users by `user`.`ID`.
     *
     * @param  Collection<int, int|string|null>  $ids
     * @return array<int, string>
     */
    private function names(Collection $ids): array
    {
        $names = [];

        foreach (User::query()->whereIn('ID', $ids->filter()->all())->get() as $person) {
            $names[(int) $person->getAuthIdentifier()] = $person->name;
        }

        return $names;
    }
}
