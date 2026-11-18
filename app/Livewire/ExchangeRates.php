<?php

namespace App\Livewire;

use App\Models\DailyRate;
use App\Models\User;
use App\Services\Ledger\Currencies;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Daily exchange rates (finance decision of 2026-11-19, one treasury): one rate per currency and day for the whole
 * organisation. The accountant proposes it, the Financial Manager approves it; a rate the Financial Manager types is
 * approved at once. It takes the place of the allocation rates per city, under the same permission (`alloc`).
 */
#[Layout('components.layouts.app')]
class ExchangeRates extends Component
{
    public string $currency = '';

    public string $day = '';

    public string $rate = '';

    public string $note = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(DailyRates $rates): void
    {
        $this->currency = $rates->currencies()[0] ?? '';
        $this->day = CarbonImmutable::today()->toDateString();
    }

    public function propose(DailyRates $rates): void
    {
        Gate::authorize('screen', ['alloc', 'create']);

        try {
            $saved = $rates->propose($this->currency, $this->day, $this->rate, $this->note, $this->user(), Gate::allows('screen', ['alloc', 'approve']));
        } catch (TreasuryException $refused) {
            $this->fail($refused);

            return;
        }

        $this->reset('rate', 'note');
        $this->say(__($saved->status === DailyRate::APPROVED ? 'treasury.rate_saved_approved' : 'treasury.rate_saved_pending', [
            'currency' => $saved->currency, 'date' => $saved->day->format('d/m/Y'),
        ]));
    }

    public function approve(int $id, DailyRates $rates): void
    {
        Gate::authorize('screen', ['alloc', 'approve']);

        try {
            $rates->approve($id, $this->user());
            $this->say(__('treasury.rate_approved'));
        } catch (TreasuryException $refused) {
            $this->fail($refused);
        }
    }

    public function reject(int $id, DailyRates $rates): void
    {
        Gate::authorize('screen', ['alloc', 'approve']);

        try {
            $rates->reject($id, $this->user());
            $this->say(__('treasury.rate_rejected'));
        } catch (TreasuryException $refused) {
            $this->fail($refused);
        }
    }

    public function render(DailyRates $rates, Currencies $currencies): View
    {
        $today = CarbonImmutable::today();
        $current = [];

        foreach ($rates->currencies() as $code) {
            $current[$code] = $rates->rateFor($code, $today);
        }

        $list = DailyRate::query()->orderByDesc('day')->orderByDesc('id')->limit(90)->get();

        return view('livewire.exchange-rates', [
            'currencies' => $rates->currencies(),
            'usdPerUnit' => array_filter($rates->currencies(), fn (string $code): bool => $currencies->isUsdPerUnit($code)),
            'current' => $current,
            'today' => $today,
            'rates' => $list,
            'people' => User::query()->whereIn('ID', $list->pluck('proposed_by')->merge($list->pluck('decided_by'))->filter()->unique()->values())->get()
                ->mapWithKeys(fn (User $person): array => [$person->getKey() => $person->username]),
            'canPropose' => Gate::allows('screen', ['alloc', 'create']),
            'canApprove' => Gate::allows('screen', ['alloc', 'approve']),
            'me' => (int) $this->user()->getKey(),
        ]);
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

    private function fail(TreasuryException $refused): void
    {
        $this->problems = $refused->problems;
        $this->notice = null;
    }
}
