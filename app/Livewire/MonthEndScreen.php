<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\User;
use App\Services\Ledger\MonthEnd;
use App\Services\Ledger\MonthEndException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Month end (F-09): per city, the count of each safe, the transfer company statement and the bank statements against the
 * ledger, then closing the month (Financial Manager) and reopening it with a reason (Head Office).
 */
#[Layout('components.layouts.app')]
class MonthEndScreen extends Component
{
    #[Url]
    public string $month = '';

    #[Url]
    public string $city = '';

    /** @var array<string, string> */
    public array $counted = [];

    /** @var array<string, string> */
    public array $note = [];

    public string $reopenReason = '';

    /** @var list<string> */
    public array $failures = [];

    public ?string $notice = null;

    public function mount(): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $this->month) !== 1) {
            $this->month = now()->subMonthNoOverflow()->format('Y-m');
        }
    }

    public function updatedMonth(): void
    {
        $this->counted = [];
        $this->note = [];
        $this->failures = [];
        $this->notice = null;
    }

    public function saveRow(MonthEnd $monthEnd, string $code): void
    {
        Gate::authorize('screen', ['monthend', 'edit']);
        $this->failures = [];
        $this->notice = null;

        try {
            $monthEnd->saveCount($this->month, $code, $this->counted[$code] ?? '', $this->note[$code] ?? '', $this->userId());
            $this->notice = __('ledger.monthend.saved', ['account' => $code]);
        } catch (MonthEndException $e) {
            $this->failures = $e->problems;
        }
    }

    public function close(MonthEnd $monthEnd): void
    {
        Gate::authorize('screen', ['monthend', 'approve']);
        $this->failures = [];
        $this->notice = null;

        try {
            $monthEnd->close($this->month, $this->userId());
            $this->notice = __('ledger.monthend.done', ['month' => $this->month]);
        } catch (MonthEndException $e) {
            $this->failures = $e->problems;
        }
    }

    public function reopen(MonthEnd $monthEnd): void
    {
        Gate::authorize('screen', ['monthend', 'delete']);
        $this->failures = [];
        $this->notice = null;

        try {
            $monthEnd->reopen($this->month, $this->userId(), $this->reopenReason);
            $this->reopenReason = '';
            $this->notice = __('ledger.monthend.reopened', ['month' => $this->month]);
        } catch (MonthEndException $e) {
            $this->failures = $e->problems;
        }
    }

    public function render(MonthEnd $monthEnd): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : now()->format('Y-m');
        $rows = $monthEnd->rows($month, $this->city);

        foreach ($rows as $row) {
            $this->counted[$row['code']] ??= $row['counted'] ?? '';
            $this->note[$row['code']] ??= $row['explanation'] ?? '';
        }

        $user = auth()->user();

        return view('livewire.month-end', [
            'rows' => $rows,
            'cities' => $monthEnd->cities(),
            'closed' => $monthEnd->isClosed($month),
            'blockers' => $monthEnd->isClosed($month) ? [] : $monthEnd->blockers($month),
            'history' => $monthEnd->history($month),
            'canEdit' => $user instanceof User && $user->canOnScreen('monthend', ScreenAction::Edit),
            'canClose' => $user instanceof User && $user->canOnScreen('monthend', ScreenAction::Approve),
            'canReopen' => $user instanceof User && $user->canOnScreen('monthend', ScreenAction::Delete),
        ]);
    }

    private function userId(): int
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return (int) $user->getAuthIdentifier();
    }
}
