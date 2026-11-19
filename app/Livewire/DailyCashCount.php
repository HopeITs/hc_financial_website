<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Treasury\CashCounts;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The daily cash count (finance decision of 2026-11-19), in place of the old tills screen: each cash or bank account of
 * a city counted at the end of the day against its book balance; a difference is explained and booked on 5505.
 */
#[Layout('components.layouts.app')]
class DailyCashCount extends Component
{
    #[Url]
    public string $day = '';

    #[Url]
    public string $city = '';

    /** @var array<string, string> account code => the amount counted */
    public array $counted = [];

    /** @var array<string, string> account code => why it differs */
    public array $explanation = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(CashCounts $counts): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day) !== 1) {
            $this->day = CarbonImmutable::today()->toDateString();
        }

        if ($this->city === '' || ! in_array($this->city, $counts->cities(), true)) {
            $this->city = $counts->cities()[0] ?? '';
        }

        $this->loadCounts($counts);
    }

    public function updatedDay(CashCounts $counts): void
    {
        $this->loadCounts($counts);
    }

    public function updatedCity(CashCounts $counts): void
    {
        $this->loadCounts($counts);
    }

    public function saveCount(string $code, CashCounts $counts): void
    {
        Gate::authorize('screen', ['tills', 'create']);

        try {
            $count = $counts->save($this->date(), $code, (string) ($this->counted[$code] ?? ''), (string) ($this->explanation[$code] ?? ''), $this->user());
        } catch (TreasuryException $refused) {
            $this->fail($refused);

            return;
        }

        $this->problems = [];
        $this->notice = bccomp((string) $count->difference, '0', 2) === 0
            ? __('treasury.count_matches', ['account' => $code])
            : __('treasury.count_differs', ['account' => $code, 'difference' => number_format((float) $count->difference, 2)]);
    }

    public function adjust(string $code, CashCounts $counts): void
    {
        Gate::authorize('screen', ['tills', 'create']);

        try {
            $voucher = $counts->adjust($this->date(), $code, $this->user());
        } catch (TreasuryException $refused) {
            $this->fail($refused);

            return;
        }

        $this->problems = [];
        $this->notice = __('treasury.adjustment_prepared', ['voucher' => $voucher->id]);
    }

    public function render(CashCounts $counts): View
    {
        return view('livewire.daily-cash-count', [
            'rows' => $counts->rows($this->date(), $this->city),
            'cities' => $counts->cities(),
            'canCount' => Gate::allows('screen', ['tills', 'create']),
        ]);
    }

    private function loadCounts(CashCounts $counts): void
    {
        $this->counted = [];
        $this->explanation = [];

        foreach ($counts->rows($this->date(), $this->city) as $row) {
            $this->counted[$row['code']] = $row['counted'] ?? '';
            $this->explanation[$row['code']] = $row['explanation'] ?? '';
        }
    }

    private function date(): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day) === 1 ? CarbonImmutable::parse($this->day) : CarbonImmutable::today();
    }

    private function fail(TreasuryException $refused): void
    {
        $this->problems = $refused->problems;
        $this->notice = null;
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
