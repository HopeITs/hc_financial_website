<?php

namespace App\Livewire;

use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Treasury\MonthlyRates;
use App\Services\Treasury\TreasuryException;
use App\Services\Treasury\TreasuryMoves;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Transfers and exchange (finance decision of 2026-11-19, one treasury): money moving inside the organisation, between
 * banks, the transfer company and the city safes and tills, or from one currency to another. No project is named; each
 * one is a voucher waiting for approval.
 */
#[Layout('components.layouts.app')]
class TreasuryTransfers extends Component
{
    private const KINDS = ['transfer', 'exchange'];

    #[Url]
    public string $kind = 'transfer';

    public string $from = '';

    public string $to = '';

    public string $day = '';

    public string $amount = '';

    public string $received = '';

    /** An exchange's fee, in the currency received (a transfer's fee is what did not arrive). */
    public string $fee = '';

    /** TreasuryMoves::FEE_TO_ORGANISATION or the key of the budget line the fee is charged to. */
    public string $feeTo = TreasuryMoves::FEE_TO_ORGANISATION;

    public string $contact = '';

    public string $center = '';

    public string $note = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        if (! in_array($this->kind, self::KINDS, true)) {
            $this->kind = 'transfer';
        }

        $this->day = CarbonImmutable::today()->toDateString();
    }

    public function updatedKind(): void
    {
        $this->reset('from', 'to', 'amount', 'received', 'fee', 'feeTo', 'contact', 'center', 'note', 'notice', 'problems');

        if (! in_array($this->kind, self::KINDS, true)) {
            $this->kind = 'transfer';
        }
    }

    public function save(TreasuryMoves $moves): void
    {
        Gate::authorize('screen', ['transfers', 'create']);

        try {
            $voucher = $this->kind === 'exchange'
                ? $moves->exchange($this->from, $this->to, $this->day, $this->amount, $this->received, $this->contact, $this->center, $this->note, $this->user(), $this->fee, $this->feeTo)
                : $moves->transfer($this->from, $this->to, $this->day, $this->amount, $this->received, $this->contact, $this->center, $this->note, $this->user(), $this->feeTo);
        } catch (TreasuryException $refused) {
            $this->problems = $refused->problems;
            $this->notice = null;

            return;
        }

        $this->reset('from', 'to', 'amount', 'received', 'fee', 'feeTo', 'contact', 'center', 'note');
        $this->problems = [];
        $this->notice = __('treasury.saved', ['voucher' => $voucher->id]);
    }

    public function render(TreasuryMoves $moves, MonthlyRates $rates): View
    {
        $accounts = $moves->accounts();
        $exchange = $this->kind === 'exchange';
        $source = $accounts->get($this->from);
        $target = $accounts->get($this->to);
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day) === 1 ? CarbonImmutable::parse($this->day) : CarbonImmutable::today();
        $arabic = app()->getLocale() === 'ar';
        $label = fn (GlAccount $account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en).' ('.$account->currency.')';
        $needs = fn (string $tag): bool => collect([$source, $target])->filter()->contains(fn (GlAccount $account): bool => in_array($tag, (array) $account->required_tags, true));
        $company = (string) config('ledger.transfer_company_account');
        $targets = $exchange
            ? $moves->exchangeTargets($source)
            : $accounts->filter(fn (GlAccount $account): bool => $source === null || ($account->code !== $source->code && $account->currency === $source->currency));

        return view('livewire.treasury-transfers', [
            'kinds' => self::KINDS,
            'accounts' => ($exchange ? $moves->exchangeSources() : $accounts)->map($label)->all(),
            'targets' => $targets->map($label)->all(),
            'source' => $source,
            'target' => $target,
            'withCompany' => in_array($company, [$this->from, $this->to], true),
            'needsCenter' => $needs('Center'),
            'rate' => $source !== null && $source->currency !== 'USD' ? $rates->forDay((string) $source->currency, $day) : null,
            'exchangeRates' => $exchange ? $moves->exchangeRates($this->from, $this->to, $this->day, $this->amount, $this->received) : null,
            'feeBudgetLines' => $moves->feeBudgetLines(),
            'recent' => Voucher::query()->whereIn('type', [VoucherType::Transfer->value, VoucherType::Exchange->value])->whereNull('transfer_kind')
                ->with('lines')->orderByDesc('date')->orderByDesc('id')->limit(20)->get(),
            'canCreate' => Gate::allows('screen', ['transfers', 'create']),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
