<?php

namespace App\Livewire;

use App\Models\DailyRate;
use App\Models\MonthlyRate;
use App\Models\User;
use App\Services\Ledger\Currencies;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\MonthlyRates;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Exchange rates (finance decisions of 2026-11-19, one treasury), for the whole organisation, in two kinds:
 *
 * - Monthly: typed by the Financial Manager from what the dollars were really exchanged at that month (shown beside it).
 *   Every invoice and expense takes it by default; a month without one uses the month before's.
 * - Daily: what the beneficiaries' instalments received that day are valued at. The accountant proposes it, the
 *   Financial Manager approves it; a rate the Financial Manager types is approved at once.
 *
 * It takes the place of the allocation rates per city, under the same permission (`alloc`).
 */
#[Layout('components.layouts.app')]
class ExchangeRates extends Component
{
    public string $currency = '';

    public string $day = '';

    public string $rate = '';

    public string $note = '';

    public string $monthlyCurrency = '';

    public string $month = '';

    public string $monthlyRate = '';

    public string $monthlyNote = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(DailyRates $rates): void
    {
        $this->currency = $this->monthlyCurrency = $rates->currencies()[0] ?? '';
        $this->day = CarbonImmutable::today()->toDateString();
        $this->month = CarbonImmutable::today()->format('Y-m');
    }

    /** The Financial Manager sets (or changes) a month's rate; the vouchers already made keep theirs. */
    public function setMonthly(MonthlyRates $monthly): void
    {
        Gate::authorize('screen', ['alloc', 'approve']);

        try {
            $saved = $monthly->set($this->month, $this->monthlyCurrency, $this->monthlyRate, $this->monthlyNote, $this->user());
        } catch (TreasuryException $refused) {
            $this->fail($refused);

            return;
        }

        $this->reset('monthlyRate', 'monthlyNote');
        $this->say(__('treasury.monthly_saved', ['currency' => $saved->currency, 'month' => CarbonImmutable::parse($saved->month.'-01')->format('m/Y')]));
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

    public function render(DailyRates $rates, MonthlyRates $monthly, Currencies $currencies): View
    {
        $today = CarbonImmutable::today();
        $current = [];
        $currentMonthly = [];

        foreach ($rates->currencies() as $code) {
            $current[$code] = $rates->rateFor($code, $today);
            $currentMonthly[$code] = $monthly->forDay($code, $today);
        }

        $list = DailyRate::query()->orderByDesc('day')->orderByDesc('id')->limit(90)->get();
        $months = MonthlyRate::query()->orderByDesc('month')->orderBy('currency')->limit(36)->get();
        $chosenMonth = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1 ? $this->month : null;
        $exchangedChosen = $chosenMonth !== null && $this->monthlyCurrency !== '' ? $monthly->exchangedIn($this->monthlyCurrency, $chosenMonth) : null;

        return view('livewire.exchange-rates', [
            'currencies' => $rates->currencies(),
            'usdPerUnit' => array_filter($rates->currencies(), fn (string $code): bool => $currencies->isUsdPerUnit($code)),
            'current' => $current,
            'currentMonthly' => $currentMonthly,
            'today' => $today,
            'rates' => $list,
            'months' => $months,
            'exchanged' => $months->mapWithKeys(fn (MonthlyRate $row): array => [$row->id => $monthly->exchangedIn($row->currency, $row->month)]),
            'exchangedChosen' => $exchangedChosen !== null ? $exchangedChosen + ['usd_label' => number_format((float) $exchangedChosen['usd'], 2)] : null,
            'people' => User::query()->whereIn('ID', $list->pluck('proposed_by')->merge($list->pluck('decided_by'))->merge($months->pluck('set_by'))->filter()->unique()->values())->get()
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
