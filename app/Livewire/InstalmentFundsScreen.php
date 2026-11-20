<?php

namespace App\Livewire;

use App\Models\FundExchange;
use App\Models\GlAccount;
use App\Models\User;
use App\Services\Loans\InstalmentFunds;
use App\Services\Loans\LoanException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Instalment funds (finance decisions of 2026-11-22): each funder's micro-loan repayments in each city, in pounds and
 * dollars, with the instalments still owed, all at today's value. The Financial Manager alone exchanges a fund's pounds
 * with the organisation (fix them in dollars, or for good) and gives fixed pounds back.
 */
#[Layout('components.layouts.app')]
class InstalmentFundsScreen extends Component
{
    public string $fund = '';

    public string $kind = FundExchange::FIX;

    public string $pounds = '';

    public string $rate = '';

    public string $poundAccount = '';

    public string $dollarAccount = '';

    public string $date = '';

    /** @var array<int, string> the give-back rate typed for each open fix */
    public array $backRate = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(InstalmentFunds $funds): void
    {
        $this->date = CarbonImmutable::today()->toDateString();
        $this->rate = $this->trim($funds->todayRate(CarbonImmutable::today()));
    }

    /** The day's rate is proposed again when the date changes; it can still be typed over. */
    public function updatedDate(InstalmentFunds $funds): void
    {
        $day = $this->day();
        if ($day !== null) {
            $this->rate = $this->trim($funds->todayRate($day));
        }
    }

    public function updatedFund(): void
    {
        $this->poundAccount = $this->dollarAccount = '';
    }

    public function exchange(InstalmentFunds $funds): void
    {
        Gate::authorize('screen', ['loanfunds', 'approve']);
        $day = $this->day();
        [$funder, $city] = array_pad(explode('|', $this->fund, 2), 2, '');

        if ($day === null || $funder === '') {
            $this->fail([$day === null ? __('funds.problem.date') : __('funds.problem.fund')]);

            return;
        }

        try {
            $saved = $funds->exchange($funder, $city, $this->kind, $this->pounds, $this->rate, $this->poundAccount, $this->dollarAccount, $day, $this->user());
        } catch (LoanException $refused) {
            $this->fail($refused->problems);

            return;
        }

        $this->reset('pounds');
        $this->problems = [];
        $this->notice = __('funds.exchanged.'.$saved->kind, ['voucher' => $saved->voucher_id, 'usd' => number_format((float) $saved->usd_amount, 2)]);
    }

    public function giveBack(int $id, InstalmentFunds $funds): void
    {
        Gate::authorize('screen', ['loanfunds', 'approve']);
        $fix = FundExchange::query()->findOrFail($id);
        $day = $this->day() ?? CarbonImmutable::today();
        $rate = (string) ($this->backRate[$id] ?? $this->rate);

        try {
            $back = $funds->giveBack($fix, $rate, $day, $this->user());
        } catch (LoanException $refused) {
            $this->fail($refused->problems);

            return;
        }

        $this->problems = [];
        $this->notice = __('funds.given_back', ['voucher' => $back->return_voucher_id, 'saving' => number_format((float) $funds->saving($back, (string) $back->return_rate), 2)]);
    }

    public function render(InstalmentFunds $funds): View
    {
        Gate::authorize('screen', ['loanfunds', 'view']);
        $today = CarbonImmutable::today();
        $rows = $funds->balances($today);
        [, $city] = array_pad(explode('|', $this->fund, 2), 2, '');
        $todayRate = $funds->todayRate($today);
        $open = FundExchange::query()->where('kind', FundExchange::FIX)->whereNull('return_voucher_id')->orderBy('fixed_on')->get();

        return view('livewire.instalment-funds', [
            'rows' => array_map(fn (array $row): array => $row + [
                'labels' => array_map(fn (string $amount): string => number_format((float) $amount, 2), array_intersect_key($row, array_flip(['syp', 'usd', 'owed_syp', 'owed_usd', 'fixed_syp', 'fixed_usd']))),
                'value_label' => $row['value_usd'] !== null ? number_format((float) $row['value_usd'], 2) : '—',
            ], $rows),
            'totalValue' => number_format((float) array_reduce($rows, fn (string $sum, array $row): string => bcadd($sum, (string) ($row['value_usd'] ?? '0'), 2), '0.00'), 2),
            'todayRate' => $this->trim($todayRate),
            'funds' => $funds->choices($today),
            'poundAccounts' => $this->accounts('SYP', $city),
            'dollarAccounts' => $this->accounts('USD', $city),
            'open' => $open->map(fn (FundExchange $fix): array => [
                'fix' => $fix,
                'syp' => number_format((float) $fix->syp_amount, 2),
                'usd' => number_format((float) $fix->usd_amount, 2),
                'rate' => $this->trim((string) $fix->rate),
                'saving' => $todayRate !== null ? number_format((float) $funds->saving($fix, $todayRate), 2) : '—',
            ])->all(),
            'history' => FundExchange::query()->orderByDesc('id')->limit(30)->get(),
            'canExchange' => Gate::allows('screen', ['loanfunds', 'approve']),
        ]);
    }

    /** @return array<string, string> the city's active cash accounts in a currency */
    private function accounts(string $currency, string $city): array
    {
        if ($city === '') {
            return [];
        }

        $arabic = app()->getLocale() === 'ar';

        return GlAccount::query()->where('is_cash', true)->where('is_active', true)->where('currency', $currency)->where('city', $city)->orderBy('code')->get()
            ->mapWithKeys(fn (GlAccount $account): array => [$account->code => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en)])->all();
    }

    private function day(): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $this->date) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $this->date ? $day : null;
    }

    private function trim(?string $rate): string
    {
        return $rate === null ? '' : rtrim(rtrim($rate, '0'), '.');
    }

    /** @param  list<string>  $problems */
    private function fail(array $problems): void
    {
        $this->problems = $problems;
        $this->notice = null;
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
