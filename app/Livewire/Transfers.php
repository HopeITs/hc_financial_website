<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Enums\TransferKind;
use App\Livewire\Concerns\HasFormDraft;
use App\Models\AllocationRate;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\BuiltTransfer;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TransferBuilder;
use App\Services\Ledger\TransferRateException;
use App\Services\Ledger\TransferRates;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * City rates and transfer vouchers. An accountant proposes the SYP rate of a city and the Financial Manager approves it;
 * the accountant then prepares transfers that move money between a city's general accounts and projects (TransferBuilder),
 * which are saved as pending vouchers and approved like any other.
 */
#[Layout('components.layouts.app')]
class Transfers extends Component
{
    use HasFormDraft;

    public string $rateCity = '';

    public string $rateValue = '';

    public string $rateFrom = '';

    public bool $rateAverageCost = false;

    public string $kind = 'syp_to_project';

    public string $date = '';

    public string $city = '';

    public string $project = '';

    public string $otherProject = '';

    public string $currency = 'SYP';

    public string $amount = '';

    public string $cashAccount = '';

    public string $contact = '';

    public string $description = '';

    public string $exchangeRate = '';

    public string $received = '';

    public string $commission = '';

    public string $sypCashAccount = '';

    /** @var list<string> */
    public array $failures = [];

    public ?string $notice = null;

    public function mount(TransferRates $rates): void
    {
        $cities = $rates->cities();
        $this->rateCity = $cities[0] ?? '';
        $this->city = $cities[0] ?? '';
        $this->rateFrom = now()->toDateString();
        $this->date = now()->toDateString();
        $this->cashAccount = $this->defaultCash();
        $this->sypCashAccount = $this->defaultSypCash();
    }

    public function updatedKind(): void
    {
        $this->cashAccount = $this->defaultCash();
        $this->sypCashAccount = $this->defaultSypCash();
    }

    public function updatedCity(): void
    {
        $this->project = '';
        $this->otherProject = '';
        $this->cashAccount = $this->defaultCash();
        $this->sypCashAccount = $this->defaultSypCash();
    }

    public function updatedCurrency(): void
    {
        $this->cashAccount = $this->defaultCash();
    }

    public function propose(TransferRates $rates): void
    {
        Gate::authorize('screen', ['alloc', 'create']);
        $this->failures = [];
        $this->notice = null;

        try {
            $rates->propose($this->rateCity, $this->rateValue, $this->rateFrom, $this->rateAverageCost, $this->userId());
        } catch (TransferRateException $e) {
            $this->failures = [$e->getMessage()];

            return;
        }

        $this->forgetDraft();
        $this->rateValue = '';
        $this->rateAverageCost = false;
        $this->notice = __('ledger.transfers.rate_proposed');
    }

    public function approve(TransferRates $rates, int $id): void
    {
        $this->decide($rates, $id, true);
    }

    public function reject(TransferRates $rates, int $id): void
    {
        $this->decide($rates, $id, false);
    }

    public function save(TransferBuilder $builder, PostVoucher $posting): void
    {
        Gate::authorize('screen', ['alloc', 'create']);
        $this->failures = [];
        $this->notice = null;

        $built = $this->built($builder);

        if ($built->draft === null) {
            $this->failures = $built->problems;

            return;
        }

        try {
            $voucher = $posting->post($built->draft);
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        } catch (Throwable $e) {
            report($e);
            $this->failures = [__('ledger.builder.save_failed')];

            return;
        }

        $this->forgetDraft();
        session()->flash('notice', __('ledger.builder.saved', ['number' => $voucher->id]));
        $this->redirectRoute('entries.vouchers', ['voucher' => $voucher->id], navigate: true);
    }

    protected function draftFields(): array
    {
        return [
            'rateCity', 'rateValue', 'rateFrom', 'rateAverageCost', 'kind', 'date', 'city', 'project', 'otherProject', 'currency',
            'amount', 'cashAccount', 'contact', 'description', 'exchangeRate', 'received', 'commission', 'sypCashAccount',
        ];
    }

    protected function draftScreen(): string
    {
        return 'alloc';
    }

    protected function draftPermission(): array
    {
        return ['alloc', 'create'];
    }

    public function render(TransferRates $rates, TransferBuilder $builder): View
    {
        $user = auth()->user();
        $canApprove = $user instanceof User && $user->canOnScreen('alloc', ScreenAction::Approve);
        $canCreate = $user instanceof User && $user->canOnScreen('alloc', ScreenAction::Create);

        $list = AllocationRate::query()->orderBy('city')->orderByDesc('effective_from')->orderByDesc('id')->get();
        $names = DB::table('user')->whereIn('ID', $list->pluck('proposed_by')->merge($list->pluck('approved_by'))->filter()->unique()->all())->pluck('Username', 'ID');

        $kind = TransferKind::tryFrom($this->kind);
        $preview = $canCreate && $this->amount !== '' ? $this->built($builder) : null;

        return view('livewire.transfers', [
            'cities' => $rates->cities(),
            'rates' => $list,
            'names' => $names,
            'userId' => $user instanceof User ? (int) $user->getAuthIdentifier() : 0,
            'canApprove' => $canApprove,
            'canCreate' => $canCreate,
            'kinds' => TransferKind::cases(),
            'selected' => $kind,
            'showCurrency' => $kind?->movesFromProject() ?? false,
            'showOther' => $kind === TransferKind::BetweenProjects,
            'showExchange' => $kind === TransferKind::LocalExchange,
            'sypCashAccounts' => $this->sypCashAccounts(),
            'projects' => $this->projects(),
            'cashAccounts' => $this->cashAccounts(),
            'preview' => $preview,
            'effectiveCurrency' => $this->effectiveCurrency(),
        ]);
    }

    private function decide(TransferRates $rates, int $id, bool $approve): void
    {
        Gate::authorize('screen', ['alloc', 'approve']);
        $this->failures = [];
        $this->notice = null;

        try {
            $approve ? $rates->approve($id, $this->userId()) : $rates->reject($id, $this->userId());
        } catch (TransferRateException $e) {
            $this->failures = [$e->getMessage()];

            return;
        }

        $this->notice = __($approve ? 'ledger.transfers.rate_approved' : 'ledger.transfers.rate_rejected');
    }

    private function built(TransferBuilder $builder): BuiltTransfer
    {
        return $builder->build(
            $this->kind, $this->date, $this->city, $this->project, $this->otherProject, $this->currency,
            $this->amount, $this->cashAccount, $this->contact, $this->description, $this->userId(),
            $this->exchangeRate, $this->received, $this->commission, $this->sypCashAccount,
        );
    }

    private function effectiveCurrency(): string
    {
        return match (TransferKind::tryFrom($this->kind)) {
            TransferKind::UsdToProject, TransferKind::LocalExchange => 'USD',
            TransferKind::SypToProject => 'SYP',
            default => $this->currency,
        };
    }

    /** @return Collection<int, GlProject> */
    private function projects(): Collection
    {
        return GlProject::query()->where('is_general_account', false)->orderBy('code')->get();
    }

    /** @return Collection<int, GlAccount> the city's cash accounts of the currency, in transit left out */
    private function cashAccounts(): Collection
    {
        return $this->cashAccountsOf($this->effectiveCurrency());
    }

    /** @return Collection<int, GlAccount> the city's pound cash accounts, for the pounds side of a local exchange */
    private function sypCashAccounts(): Collection
    {
        return $this->cashAccountsOf('SYP');
    }

    /** @return Collection<int, GlAccount> */
    private function cashAccountsOf(string $currency): Collection
    {
        return GlAccount::query()
            ->where('is_cash', true)->where('level', 'posting')->where('is_active', true)
            ->where('city', $this->city)->where('currency', $currency)
            ->orderBy('code')->get()
            ->reject(fn (GlAccount $account) => preg_match((string) config('ledger.in_transit_pattern'), $account->code) === 1)
            ->values();
    }

    private function defaultCash(): string
    {
        return $this->cashAccounts()->first()->code ?? '';
    }

    private function defaultSypCash(): string
    {
        return $this->sypCashAccounts()->first()->code ?? '';
    }

    private function userId(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }
}
