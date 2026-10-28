<?php

namespace App\Services\Requests;

use App\Enums\RequestCategory;
use App\Enums\RequestStatus;
use App\Enums\RoleId;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\RequestApproval;
use App\Models\RequestAudit;
use App\Models\RequestQuote;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use App\Services\Ledger\ProjectAverageRate;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The life of a project manager's request (plan 6.9): draft, submitted, approved or rejected, then closed.
 *
 * The manager edits a request until a decision has been made on it, and every edit goes to the audit trail. The
 * Financial Manager decides first; above USD 2,000 (config) the General Manager approves after, so the request stays
 * "submitted" until both have. Who may act is the RequestPolicy; this class checks it again and owns the states.
 */
class RequestWorkflow
{
    public function __construct(private readonly ApprovalChain $chain, private readonly ProjectAverageRate $averageRate) {}

    /**
     * @param  array{project: string, type?: string, month?: string, category?: string|null, description?: string|null}  $header
     * @param  array<int, array<string, string>>  $lines
     *
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function create(User $by, array $header, array $lines): LedgerRequest
    {
        $this->authorize($by, 'createFor', LedgerRequest::class, $header['project']);

        $clean = $this->validated($header, $lines, null);

        return DB::transaction(function () use ($by, $clean) {
            $request = LedgerRequest::query()->create($clean['header'] + [
                'currency' => 'USD', 'amount' => $clean['total'], 'status' => RequestStatus::Draft->value, 'requested_by' => $this->id($by),
            ]);
            $request->lines()->createMany($clean['lines']);
            $this->audit($request, $by, 'create', reason: null);

            return $request->refresh();
        });
    }

    /**
     * @param  array{project: string, type?: string, month?: string, category?: string|null, description?: string|null}  $header
     * @param  array<int, array<string, string>>  $lines
     *
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function update(User $by, LedgerRequest $request, array $header, array $lines): LedgerRequest
    {
        $this->authorize($by, 'update', $request);

        return DB::transaction(function () use ($by, $request, $header, $lines) {
            $request = $this->locked($request);
            $this->assertEditable($request);

            if ($header['project'] !== $request->project_code) {
                $this->authorize($by, 'createFor', LedgerRequest::class, $header['project']);
            }

            $clean = $this->validated($header, $lines, $request);

            if ($request->state() === RequestStatus::Submitted) {
                $this->assertQuotes($clean['header']['category'], $request->quotes()->count());
            }

            $before = $this->snapshot($request);
            $request->update($clean['header'] + ['amount' => $clean['total']]);
            $request->lines()->delete();
            $request->lines()->createMany($clean['lines']);
            $request->unsetRelation('lines');

            $after = $this->snapshot($request->refresh());

            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $this->audit($request, $by, 'edit', $field, $before[$field], $value);
                }
            }

            return $request;
        });
    }

    /**
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function addQuote(User $by, LedgerRequest $request, UploadedFile $file): RequestQuote
    {
        $this->authorize($by, 'update', $request);

        $request = $this->locked($request);
        $this->assertEditable($request);

        if ($request->quotes()->count() >= RequestCategory::MAX_QUOTES) {
            throw new RequestException([__('requests.problem.quotes_full', ['max' => RequestCategory::MAX_QUOTES])]);
        }

        $name = Str::uuid()->toString().'.'.($file->guessExtension() ?: 'bin');
        $path = $file->storeAs('request-quotes/'.$request->id, $name, 'local');

        if ($path === false) {
            throw new RequestException([__('requests.problem.upload_failed')]);
        }

        $quote = $request->quotes()->create([
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'stored_path' => $path,
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'uploaded_by' => $this->id($by),
        ]);

        $this->audit($request, $by, 'quote_add', 'quotes', null, $quote->original_name);

        return $quote;
    }

    /**
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function removeQuote(User $by, LedgerRequest $request, int $quoteId): void
    {
        $this->authorize($by, 'update', $request);

        $request = $this->locked($request);
        $this->assertEditable($request);

        $quote = $request->quotes()->whereKey($quoteId)->first();

        if ($quote === null) {
            return;
        }

        if ($request->state() === RequestStatus::Submitted) {
            $this->assertQuotes($request->category, $request->quotes()->count() - 1);
        }

        Storage::disk('local')->delete($quote->stored_path);
        $quote->delete();
        $this->audit($request, $by, 'quote_remove', 'quotes', $quote->original_name, null);
    }

    /**
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function submit(User $by, LedgerRequest $request): LedgerRequest
    {
        $this->authorize($by, 'update', $request);

        return DB::transaction(function () use ($by, $request) {
            $request = $this->locked($request);

            if ($request->state() !== RequestStatus::Draft) {
                throw new RequestException([__('requests.problem.not_draft')]);
            }

            $problems = [];

            if ($request->lines()->count() === 0) {
                $problems[] = __('requests.problem.no_lines');
            }

            $quotes = $request->quotes()->count();
            $category = $request->categoryType();
            if ($category !== null && ($quotes < RequestCategory::MIN_QUOTES || $quotes > RequestCategory::MAX_QUOTES)) {
                $problems[] = __('requests.problem.quotes_needed', ['category' => $category->label(), 'min' => RequestCategory::MIN_QUOTES, 'max' => RequestCategory::MAX_QUOTES]);
            }

            if ($problems !== []) {
                throw new RequestException($problems);
            }

            $request->update(['status' => RequestStatus::Submitted->value, 'submitted_at' => now()]);
            $this->audit($request, $by, 'submit', 'status', RequestStatus::Draft->value, RequestStatus::Submitted->value);

            return $request->refresh();
        });
    }

    /**
     * One approval, in order: the Program Manager, the Financial Manager, then (above the threshold) the General Manager.
     *
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function approve(User $by, LedgerRequest $request): LedgerRequest
    {
        return DB::transaction(function () use ($by, $request) {
            $request = $this->locked($request);
            $this->assertSubmitted($request);
            $this->authorize($by, 'decide', $request);

            $done = $this->approvedRoles($request);
            $role = $this->chain->nextStep($this->total($request), $done, true);
            abort_if($role === null, 403);

            RequestApproval::query()->create([
                'request_id' => $request->id, 'step' => count($done) + 1, 'role_id' => $role->value, 'decision' => 'approved',
                'decided_by' => $this->id($by), 'decided_at' => now(),
            ]);

            $this->audit($request, $by, 'approve', 'step', null, (string) (count($done) + 1));

            if ($this->chain->nextStep($this->total($request), [...$done, $role], true) === null) {
                $request->update(['status' => RequestStatus::Approved->value, 'decided_by' => $this->id($by), 'decided_at' => now()]);
                $this->audit($request, $by, 'approved', 'status', RequestStatus::Submitted->value, RequestStatus::Approved->value);
            }

            return $request->refresh();
        });
    }

    /**
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function reject(User $by, LedgerRequest $request, string $reason): LedgerRequest
    {
        return DB::transaction(function () use ($by, $request, $reason) {
            $request = $this->locked($request);
            $this->assertSubmitted($request);
            $this->authorize($by, 'decide', $request);

            $reason = trim($reason);
            if ($reason === '') {
                throw new RequestException([__('requests.problem.reason_needed')]);
            }

            $done = $this->approvedRoles($request);
            $role = $this->chain->nextStep($this->total($request), $done, true);
            abort_if($role === null, 403);

            RequestApproval::query()->create([
                'request_id' => $request->id, 'step' => count($done) + 1, 'role_id' => $role->value, 'decision' => 'rejected',
                'decided_by' => $this->id($by), 'reason' => $reason, 'decided_at' => now(),
            ]);

            $request->update([
                'status' => RequestStatus::Rejected->value, 'decided_by' => $this->id($by), 'decided_at' => now(), 'reject_reason' => $reason,
            ]);
            $this->audit($request, $by, 'reject', 'status', RequestStatus::Submitted->value, RequestStatus::Rejected->value, $reason);

            return $request->refresh();
        });
    }

    /**
     * An approved request ends here; a closed one is never opened again, a new request is made instead.
     *
     * @throws AuthorizationException
     * @throws RequestException
     */
    public function close(User $by, LedgerRequest $request): LedgerRequest
    {
        $this->authorize($by, 'close', $request);

        return DB::transaction(function () use ($by, $request) {
            $request = $this->locked($request);

            if ($request->state() !== RequestStatus::Approved) {
                throw new RequestException([__('requests.problem.not_approved')]);
            }

            $request->update(['status' => RequestStatus::Closed->value, 'closed_at' => now()]);
            $this->audit($request, $by, 'close', 'status', RequestStatus::Approved->value, RequestStatus::Closed->value);

            return $request->refresh();
        });
    }

    /** Whether the request can still be edited: a draft, or a submitted request nobody has decided on yet. */
    public function isEditable(LedgerRequest $request): bool
    {
        return match ($request->state()) {
            RequestStatus::Draft => true,
            RequestStatus::Submitted => ! $request->approvals()->exists(),
            default => false,
        };
    }

    /**
     * The role whose turn it is, or null when no decision is pending.
     */
    public function pendingRole(LedgerRequest $request): ?RoleId
    {
        if ($request->state() !== RequestStatus::Submitted) {
            return null;
        }

        return $this->chain->nextStep($this->total($request), $this->approvedRoles($request), true);
    }

    /**
     * Budget lines this request would take past their budget: a warning only, never a refusal.
     *
     * @return list<array{key: string, budget: string, spent: string, requested: string, currency: string}>
     */
    public function budgetWarnings(LedgerRequest $request): array
    {
        $warnings = [];

        foreach ($request->lines->groupBy('budget_line_key') as $key => $group) {
            $budgetLine = GlBudgetLine::query()->find($key);

            if ($budgetLine === null) {
                continue;
            }

            $requested = Money::zero();
            foreach ($group as $line) {
                $requested = bcadd($requested, $line->currency === $budgetLine->currency ? Money::normalize($line->amount) : '0', 2);
            }

            $spent = DB::table('gl_voucher_lines as l')
                ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
                ->where('l.budget_line_key', $key)
                ->where('l.currency', $budgetLine->currency)
                ->where('v.status', 'approved')
                ->whereNull('v.deleted_at')
                ->selectRaw('coalesce(sum(l.debit_orig) - sum(l.credit_orig), 0) as spent')
                ->value('spent');
            $spent = Money::normalize(is_numeric($spent) ? (string) $spent : null);

            if (bccomp(bcadd($spent, $requested, 2), Money::normalize($budgetLine->budget), 2) > 0) {
                $warnings[] = ['key' => (string) $key, 'budget' => Money::normalize($budgetLine->budget), 'spent' => $spent, 'requested' => $requested, 'currency' => $budgetLine->currency];
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, string>>  $lines
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>, total: numeric-string}
     *
     * @throws RequestException
     */
    private function validated(array $header, array $lines, ?LedgerRequest $current): array
    {
        $problems = [];
        $project = GlProject::query()->whereKey((string) ($header['project'] ?? ''))->first();

        if ($project === null) {
            throw new RequestException([__('requests.problem.no_project')]);
        }

        $type = ($header['type'] ?? LedgerRequest::MONTHLY) === LedgerRequest::EXTRA ? LedgerRequest::EXTRA : LedgerRequest::MONTHLY;
        $month = $this->month((string) ($header['month'] ?? ''));

        if ($month === null) {
            if ($type === LedgerRequest::MONTHLY) {
                $problems[] = __('requests.problem.month_needed');
            }
            $month = now()->startOfMonth();
        }

        if ($type === LedgerRequest::MONTHLY) {
            $taken = LedgerRequest::query()
                ->where('project_code', $project->code)
                ->where('type', LedgerRequest::MONTHLY)
                ->whereDate('month', $month->toDateString())
                ->where('status', '!=', RequestStatus::Rejected->value)
                ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
                ->exists();

            if ($taken) {
                $problems[] = __('requests.problem.month_taken', ['project' => $project->code, 'month' => $month->format('Y-m')]);
            }
        }

        $categoryValue = $header['category'] ?? null;
        $category = is_string($categoryValue) && $categoryValue !== '' ? RequestCategory::tryFrom($categoryValue) : null;
        if (is_string($categoryValue) && $categoryValue !== '' && $category === null) {
            $problems[] = __('requests.problem.bad_category');
        }

        $clean = [];
        $total = Money::zero();
        $seen = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $key = trim((string) ($line['budget_line'] ?? ''));
            $amountText = trim((string) ($line['amount'] ?? ''));

            if ($key === '' && $amountText === '') {
                continue;
            }

            $budgetLine = $key !== '' ? GlBudgetLine::query()->find($key) : null;

            if ($budgetLine === null || $budgetLine->project_code !== $project->code) {
                $problems[] = __('requests.problem.line_budget', ['line' => $number]);

                continue;
            }

            if (isset($seen[$key])) {
                $problems[] = __('requests.problem.line_duplicate', ['line' => $number]);

                continue;
            }
            $seen[$key] = true;

            $amount = preg_match('/^\d+(\.\d{1,2})?$/', $amountText) === 1 ? Money::normalize($amountText) : null;
            if ($amount === null || bccomp($amount, '0', 2) <= 0) {
                $problems[] = __('requests.problem.line_amount', ['line' => $number]);

                continue;
            }

            $currency = strtoupper(trim((string) ($line['currency'] ?? ''))) ?: (in_array($budgetLine->currency, ['USD', 'SYP'], true) ? $budgetLine->currency : 'USD');
            if (! in_array($currency, ['USD', 'SYP'], true)) {
                $problems[] = __('requests.problem.line_currency', ['line' => $number]);

                continue;
            }

            $rate = Money::normalize('1', 6);
            if ($currency === 'SYP') {
                $rateText = trim((string) ($line['rate'] ?? ''));
                $rateText = $rateText !== '' ? $rateText : ($this->averageRate->rate($project->code) ?? '');

                $rate = preg_match('/^\d+(\.\d+)?$/', $rateText) === 1 ? Money::normalize($rateText, 6) : null;
                if ($rate === null || bccomp($rate, '0', 6) <= 0) {
                    $problems[] = __('requests.problem.line_rate', ['line' => $number]);

                    continue;
                }
            }

            $usd = $currency === 'USD' ? $amount : Money::normalize(bcdiv($amount, $rate, 6));
            $total = bcadd($total, $usd, 2);

            $note = trim((string) ($line['note'] ?? ''));
            $clean[] = [
                'budget_line_key' => $key, 'amount' => $amount, 'currency' => $currency, 'rate' => $rate, 'amount_usd' => $usd,
                'note' => $note !== '' ? Str::limit($note, 250, '') : null,
            ];
        }

        if ($clean === [] && $problems === []) {
            $problems[] = __('requests.problem.no_lines');
        }

        if ($problems !== []) {
            throw new RequestException($problems);
        }

        $description = trim((string) ($header['description'] ?? ''));

        return [
            'header' => [
                'type' => $type, 'project_code' => $project->code, 'month' => $month->toDateString(), 'category' => $category?->value,
                'description' => $description !== '' ? $description : null,
            ],
            'lines' => $clean,
            'total' => $total,
        ];
    }

    private function month(string $value): ?Carbon
    {
        if (preg_match('/^(\d{4})-(\d{2})(-\d{2})?$/', $value, $match) !== 1 || (int) $match[2] < 1 || (int) $match[2] > 12) {
            return null;
        }

        return Carbon::create((int) $match[1], (int) $match[2], 1)->startOfDay();
    }

    /** @return array<string, string> */
    private function snapshot(LedgerRequest $request): array
    {
        return [
            'type' => $request->type,
            'project' => $request->project_code,
            'month' => $request->month?->format('Y-m') ?? '',
            'category' => (string) $request->category,
            'description' => (string) $request->description,
            'lines' => $request->lines()->get()->map(fn ($line) => $line->budget_line_key.' '.$line->amount.' '.$line->currency.($line->currency === 'SYP' ? ' @'.$line->rate : ''))->implode('; '),
            'total_usd' => (string) $request->amount,
        ];
    }

    private function assertEditable(LedgerRequest $request): void
    {
        if (! $this->isEditable($request)) {
            throw new RequestException([__('requests.problem.locked')]);
        }
    }

    private function assertSubmitted(LedgerRequest $request): void
    {
        if ($request->state() !== RequestStatus::Submitted) {
            throw new RequestException([__('requests.problem.not_submitted')]);
        }
    }

    private function assertQuotes(?string $category, int $quotes): void
    {
        $category = $category !== null ? RequestCategory::tryFrom($category) : null;

        if ($category !== null && ($quotes < RequestCategory::MIN_QUOTES || $quotes > RequestCategory::MAX_QUOTES)) {
            throw new RequestException([__('requests.problem.quotes_needed', ['category' => $category->label(), 'min' => RequestCategory::MIN_QUOTES, 'max' => RequestCategory::MAX_QUOTES])]);
        }
    }

    /** @return list<RoleId> */
    private function approvedRoles(LedgerRequest $request): array
    {
        return array_values($request->approvals()->where('decision', 'approved')->get()->map(fn (RequestApproval $approval): RoleId => $approval->role_id)->all());
    }

    /** @return numeric-string */
    private function total(LedgerRequest $request): string
    {
        return Money::normalize((string) $request->amount);
    }

    private function locked(LedgerRequest $request): LedgerRequest
    {
        return LedgerRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
    }

    private function id(User $user): int
    {
        return (int) $user->getAuthIdentifier();
    }

    /** @throws AuthorizationException */
    private function authorize(User $user, string $ability, mixed ...$arguments): void
    {
        Gate::forUser($user)->authorize($ability, count($arguments) === 1 ? $arguments[0] : $arguments);
    }

    private function audit(LedgerRequest $request, User $by, string $action, ?string $field = null, ?string $old = null, ?string $new = null, ?string $reason = null): void
    {
        RequestAudit::query()->create([
            'request_id' => $request->id, 'action' => $action, 'field' => $field, 'old_value' => $old, 'new_value' => $new,
            'changed_by' => $this->id($by), 'reason' => $reason,
        ]);
    }
}
