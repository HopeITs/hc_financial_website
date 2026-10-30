<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\Concerns\HasMonthFilter;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\ReverseVoucher;
use App\Services\Ledger\VoucherApprovals;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The vouchers: a list with type, status, date and search filters, and one voucher with its lines, attachments and
 * audit trail. The financial manager approves or rejects a pending voucher here (never one they prepared); a
 * rejected voucher keeps its number, is soft deleted and shows its reason.
 */
#[Layout('components.layouts.app')]
class Vouchers extends Component
{
    use HasMonthFilter;
    use WithPagination;

    public const PER_PAGE = 25;

    #[Url(as: 'voucher')]
    public ?string $number = null;

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'q')]
    public string $search = '';

    /** Whether the list also shows rejected (soft deleted) vouchers next to the others. */
    #[Url(as: 'rejected')]
    public bool $withRejected = false;

    /** Only the vouchers the signed-in person prepared (the home page's "my rejected vouchers" opens this). */
    #[Url]
    public bool $mine = false;

    /** @var list<string> Numbers ticked in the list; they stay ticked across pages. */
    public array $selected = [];

    public bool $showReject = false;

    public bool $showReverse = false;

    public string $reverseReason = '';

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
        if (in_array($name, ['type', 'status', 'from', 'to', 'search', 'withRejected', 'mine', 'month'], true)) {
            $this->resetPage();
        }
    }

    public function setType(string $type): void
    {
        $this->type = VoucherType::tryFrom($type) !== null ? $type : '';
        $this->resetPage();
    }

    /**
     * Ticks every voucher of the shown page, or clears them when they are all ticked already.
     *
     * @param  list<string>  $numbers
     */
    public function togglePage(array $numbers): void
    {
        $numbers = array_values(array_filter(array_map(strval(...), array_slice($numbers, 0, self::PER_PAGE))));
        $allTicked = $numbers !== [] && array_diff($numbers, $this->selected) === [];

        $this->selected = array_values($allTicked ? array_diff($this->selected, $numbers) : array_unique([...$this->selected, ...$numbers]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function approve(VoucherApprovals $approvals): void
    {
        Gate::authorize('screen', ['vouchers', 'approve']);
        $this->reset('notice', 'failures');

        $voucher = $this->pending();

        try {
            $approvals->approve($voucher, $this->user());
            $this->notice = __('vouchers.approved_notice', ['number' => $voucher->id]);
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);
        }
    }

    public function openReject(): void
    {
        Gate::authorize('screen', ['vouchers', 'approve']);

        $this->reset('notice', 'failures', 'rejectReason');
        $this->resetErrorBag();
        $this->showReject = true;
    }

    public function openReverse(): void
    {
        Gate::authorize('screen', ['vouchers', 'create']);
        $this->reverseReason = '';
        $this->failures = [];
        $this->showReverse = true;
    }

    public function closeReverse(): void
    {
        $this->showReverse = false;
    }

    /** Creates the pending reversal of the open voucher and opens it. */
    public function reverse(ReverseVoucher $reversal): void
    {
        Gate::authorize('screen', ['vouchers', 'create']);
        $this->failures = [];
        $voucher = $this->load((string) $this->number);
        abort_if($voucher === null, 404);

        try {
            $created = $reversal->reverse($voucher, (int) $this->user()->getAuthIdentifier(), $this->reverseReason);
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => (string) $violation->message, $e->violations);

            return;
        }

        $this->showReverse = false;
        session()->flash('notice', __('vouchers.reverse.created', ['number' => $created->id]));
        $this->redirectRoute('entries.vouchers', ['voucher' => $created->id], navigate: true);
    }

    public function closeReject(): void
    {
        $this->showReject = false;
    }

    public function reject(VoucherApprovals $approvals): void
    {
        Gate::authorize('screen', ['vouchers', 'approve']);
        $this->reset('notice', 'failures');
        $this->resetErrorBag();

        $voucher = $this->pending();

        try {
            $approvals->reject($voucher, $this->user(), $this->rejectReason);
            $this->showReject = false;
            $this->notice = __('vouchers.rejected_notice', ['number' => $voucher->id]);
        } catch (LedgerRuleException $e) {
            $messages = array_map(fn ($violation) => $violation->message, $e->violations);

            if (trim($this->rejectReason) === '') {
                $this->addError('rejectReason', __('ledger.rules.reject_reason'));
            } else {
                $this->failures = $messages;
                $this->showReject = false;
            }
        }
    }

    public function render(): View
    {
        $current = $this->number !== null && $this->number !== '' ? $this->load($this->number) : null;

        if ($current !== null) {
            return view('livewire.vouchers', ['current' => $current, 'list' => null] + $this->detail($current));
        }

        return view('livewire.vouchers', ['current' => null] + $this->listing());
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** The open voucher, which must still be pending approval and not rejected. */
    private function pending(): Voucher
    {
        $voucher = $this->number !== null ? Voucher::query()->find($this->number) : null;
        abort_if($voucher === null, 404);

        return $voucher;
    }

    private function load(string $number): ?Voucher
    {
        return Voucher::withTrashed()->with(['lines.account', 'lines.project', 'attachments', 'audits'])->find($number);
    }

    /** @return array<string, mixed> */
    private function detail(Voucher $voucher): array
    {
        $user = auth()->user();
        $pending = $voucher->status === VoucherStatus::PendingApproval && ! $voucher->trashed();
        $mayApprove = $pending && $user instanceof User && $user->canOnScreen('vouchers', ScreenAction::Approve);
        $own = $user instanceof User && $voucher->prepared_by === (int) $user->getAuthIdentifier();
        $audits = $voucher->audits->sortBy('id')->values();

        $ids = collect([$voucher->prepared_by, $voucher->approved_by])->merge($audits->pluck('changed_by'))->filter()->unique()->values();
        $rejection = $voucher->trashed() ? $audits->where('action', 'reject')->last() : null;

        return [
            'voucher' => $voucher,
            'audits' => $audits,
            'names' => $this->names($ids),
            'showActions' => $mayApprove,
            'canApprove' => $mayApprove && Gate::allows('approve', $voucher),
            'isOwn' => $own,
            'canReverse' => $user instanceof User && $user->canOnScreen('vouchers', ScreenAction::Create) && app(ReverseVoucher::class)->problem($voucher) === null,
            'reversedBy' => Voucher::query()->where('reverses_id', $voucher->id)->value('id'),
            'rejection' => $rejection,
            'totalDebit' => $voucher->lines->reduce(fn (string $sum, $line) => bcadd($sum, Money::normalize($line->debit_usd), 2), Money::zero()),
            'totalCredit' => $voucher->lines->reduce(fn (string $sum, $line) => bcadd($sum, Money::normalize($line->credit_usd), 2), Money::zero()),
        ];
    }

    /** @return array<string, mixed> */
    private function listing(): array
    {
        $day = fn (string $value): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
        $term = trim($this->search);
        $from = $day($this->from);
        $to = $day($this->to);

        $list = Voucher::query()
            ->when($this->status === 'rejected', fn ($query) => $query->onlyTrashed())
            ->when($this->withRejected && $this->status === '', fn ($query) => $query->withTrashed())
            ->when(VoucherStatus::tryFrom($this->status) !== null, fn ($query) => $query->where('status', $this->status))
            ->when(VoucherType::tryFrom($this->type) !== null, fn ($query) => $query->where('type', $this->type))
            ->when($this->mine, fn ($query) => $query->where('prepared_by', (int) $this->user()->getAuthIdentifier()))
            ->when($from !== null, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('date', '<=', $to))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('id', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $amounts = DB::table('gl_voucher_lines')
            ->whereIn('voucher_id', $list->getCollection()->pluck('id')->all())
            ->selectRaw('voucher_id, sum(debit_usd) as total')
            ->groupBy('voucher_id')
            ->pluck('total', 'voucher_id');

        $pageIds = $list->getCollection()->pluck('id')->all();
        $selected = array_values(array_unique($this->selected));

        return [
            'list' => $list,
            'amounts' => $amounts,
            'amended' => DB::table('gl_voucher_audit')->whereIn('voucher_id', $pageIds)->where('action', 'amend')->distinct()->pluck('voucher_id')->all(),
            'pageIds' => $pageIds,
            'pageTotal' => $amounts->reduce(fn (string $sum, $total) => bcadd($sum, Money::normalize($total), 2), Money::zero()),
            'selectedTotal' => $selected === [] ? Money::zero() : Money::normalize(DB::table('gl_voucher_lines')->whereIn('voucher_id', $selected)->sum('debit_usd')),
            'names' => $this->names($list->getCollection()->pluck('prepared_by')->unique()->values()),
            'types' => VoucherType::cases(),
            'statuses' => [...array_map(fn (VoucherStatus $status) => $status->value, VoucherStatus::cases()), 'rejected'],
            'pendingCount' => Voucher::query()->where('status', VoucherStatus::PendingApproval->value)->count(),
        ];
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
