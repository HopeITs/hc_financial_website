<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Treasury\CashBook;
use App\Services\Treasury\Revaluations;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The liquidity position (finance decision of 2026-11-20), in place of the old SYP balances screen: where the money is
 * on a day (banks, the transfer company, each city's safes and tills), in each account's currency, at book dollars and
 * at today's value; and the month's revaluation of the money held in other currencies (Revaluations).
 */
#[Layout('components.layouts.app')]
class LiquidityPosition extends Component
{
    #[Url(as: 'date')]
    public string $asAt = '';

    public string $month = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->asAt) !== 1) {
            $this->asAt = CarbonImmutable::today()->toDateString();
        }

        $this->month = CarbonImmutable::today()->subMonthNoOverflow()->format('Y-m');
    }

    public function revalue(Revaluations $revaluations): void
    {
        Gate::authorize('screen', ['treasury', 'create']);

        try {
            $voucher = $revaluations->prepare($this->month, $this->user());
        } catch (TreasuryException $refused) {
            $this->problems = $refused->problems;
            $this->notice = null;

            return;
        }

        $this->problems = [];
        $this->notice = __('treasury.reval_prepared', ['voucher' => $voucher->id]);
    }

    public function render(CashBook $book, Revaluations $revaluations): View
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->asAt) === 1 ? CarbonImmutable::parse($this->asAt) : CarbonImmutable::today();
        $validMonth = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1;

        $position = $book->position($day);

        return view('livewire.liquidity-position', [
            'position' => $position,
            'totals' => ['value' => number_format((float) $position['total_value'], 2), 'book' => number_format((float) $position['total_usd'], 2)],
            'reval' => $validMonth ? $revaluations->preview($this->month) : null,
            'canRevalue' => Gate::allows('screen', ['treasury', 'create']),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
