<?php

namespace App\Livewire;

use App\Enums\ContractRestriction;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Donor;
use App\Services\Contracts\ContractBudgets;
use App\Services\Ledger\Currencies;
use App\Services\Projects\ProjectAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The funding contracts (finance design of 2026-11-16): the list, and the form that registers a new contract as a
 * draft. The contract page (ContractPage) then takes its projects, instalments, reports, approval and closing.
 * Screen `contracts`: the accountant, the Financial Manager and Head Office prepare; the Financial Manager approves.
 */
#[Layout('components.layouts.app')]
class Contracts extends Component
{
    public string $search = '';

    #[Url]
    public string $donor = '';

    public string $statusFilter = 'open';

    public bool $showForm = false;

    public string $donorId = '';

    public string $title = '';

    public string $donorReference = '';

    public string $signedOn = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $currency = 'USD';

    public string $amount = '';

    public string $fixedRate = '1';

    public string $restriction = 'restricted';

    public bool $isYearlyGeneral = false;

    public string $notes = '';

    public function add(): void
    {
        Gate::authorize('screen', ['contracts', 'create']);

        $this->resetValidation();
        $this->reset('donorId', 'title', 'donorReference', 'signedOn', 'startsOn', 'endsOn', 'amount', 'notes', 'isYearlyGeneral');
        $this->currency = 'USD';
        $this->fixedRate = '1';
        $this->restriction = ContractRestriction::Restricted->value;
        $this->donorId = $this->donor;
        $this->showForm = true;
    }

    public function updatedDonorId(string $value): void
    {
        $currency = Donor::query()->whereKey($value)->value('currency');

        if (is_string($currency) && $currency !== '') {
            $this->currency = $currency;
        }
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->showForm = false;
    }

    public function save(Currencies $currencies): void
    {
        Gate::authorize('screen', ['contracts', 'create']);

        if ($this->currency === 'USD') {
            $this->fixedRate = '1';
        }

        $data = $this->validate([
            'donorId' => ['required', Rule::exists('gl_donors', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'donorReference' => ['nullable', 'string', 'max:255'],
            'signedOn' => ['nullable', 'date'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'currency' => ['required', Rule::in($currencies->codes())],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'fixedRate' => ['required', 'numeric', 'gt:0'],
            'restriction' => ['required', Rule::enum(ContractRestriction::class)],
            'isYearlyGeneral' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $contract = Contract::query()->create([
            'number' => Contract::nextNumber((int) date('Y', (int) strtotime($data['startsOn']))),
            'donor_reference' => trim((string) $data['donorReference']) ?: null,
            'donor_id' => (int) $data['donorId'],
            'title' => trim($data['title']),
            'signed_on' => $data['signedOn'] ?: null,
            'starts_on' => $data['startsOn'],
            'ends_on' => $data['endsOn'],
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'fixed_rate' => $data['fixedRate'],
            'restriction' => $data['restriction'],
            'is_yearly_general' => $data['isYearlyGeneral'],
            'status' => ContractStatus::Draft->value,
            'notes' => trim((string) $data['notes']) ?: null,
            'created_by' => (int) Auth::id(),
        ]);

        $this->redirectRoute('funding.agreements.show', ['contract' => $contract->id], navigate: true);
    }

    public function render(ProjectAccess $access, Currencies $currencies, ContractBudgets $budgets): View
    {
        $user = Auth::user();

        $contracts = Contract::query()
            ->with('donor')
            ->visibleTo($user === null ? [] : $access->limitedTo($user))
            ->when($this->donor !== '', fn (Builder $query) => $query->where('donor_id', (int) $this->donor))
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhere('title', 'like', '%'.$this->search.'%')
                ->orWhere('donor_reference', 'like', '%'.$this->search.'%')))
            ->when($this->statusFilter === 'open', fn (Builder $query) => $query->whereNotIn('status', [ContractStatus::Ended->value, ContractStatus::Cancelled->value]))
            ->when($this->statusFilter === 'closed', fn (Builder $query) => $query->whereIn('status', [ContractStatus::Ended->value, ContractStatus::Cancelled->value]))
            ->orderByDesc('starts_on')
            ->orderByDesc('number')
            ->get();

        return view('livewire.contracts', [
            'contracts' => $contracts,
            'needsMatching' => $budgets->mismatchedIds($contracts->filter(fn (Contract $contract): bool => $contract->isApproved() && $contract->isOpen())),
            'usd' => $contracts->mapWithKeys(fn (Contract $contract) => [$contract->id => $currencies->toUsd($contract->currency, (string) $contract->amount, (string) $contract->fixed_rate)]),
            'donors' => Donor::query()->active()->orderBy('code')->get(),
            'filterDonors' => Donor::query()->orderBy('code')->get(),
            'currencyCodes' => $currencies->codes(),
            'restrictions' => ContractRestriction::cases(),
            'canCreate' => Gate::allows('screen', ['contracts', 'create']),
        ]);
    }
}
