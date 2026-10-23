<?php

namespace App\Livewire;

use App\Enums\FundSource;
use App\Enums\ScreenAction;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\PayrollSplit;
use App\Models\User;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\PayrollSplits;
use App\Services\Payroll\SalaryAllocation;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The monthly payroll: the percentages that split each employee's salary over projects and budget lines, and the
 * allocation voucher (ALC) built from them. The same voucher also shares one invoice between projects with lines typed by
 * hand. Only the finance roles and Head Office open it (config/permissions.php); the voucher is saved as pending approval.
 */
#[Layout('components.layouts.app')]
class Salaries extends Component
{
    private const BLANK_SPLIT = ['budget_line' => '', 'percent' => ''];

    private const BLANK_LINE = ['budget_line' => '', 'amount' => '', 'percent' => ''];

    #[Url]
    public string $tab = 'allocate';

    public string $employeeId = '';

    public string $employeeName = '';

    public string $changeDate = '';

    /** @var array<int, array<string, string>> */
    public array $splitRows = [];

    /** salary: lines proposed from the payroll split; invoice: lines typed by hand */
    public string $mode = 'salary';

    public string $month = '';

    public string $date = '';

    public string $amount = '';

    public string $currency = 'USD';

    public string $rate = '';

    public string $cashAccount = '';

    public string $fundSource = 'donor_grant';

    public string $description = '';

    /** @var array<int, array<string, string>> */
    public array $lines = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $failures = [];

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->date = now()->toDateString();
        $this->changeDate = now()->toDateString();
        $this->splitRows = [self::BLANK_SPLIT];
        $this->lines = [self::BLANK_LINE];
    }

    public function updatedMode(): void
    {
        $this->resetFeedback();
        $this->lines = [self::BLANK_LINE];
    }

    public function updatedEmployeeId(): void
    {
        $this->resetFeedback();
    }

    /** Fills the split editor with the split that is in force for the month of the change date. */
    public function loadSplit(PayrollSplits $splits): void
    {
        $this->resetFeedback();
        $this->guard(ScreenAction::View);

        $day = $this->day($this->changeDate) ?? CarbonImmutable::today();
        $current = $splits->forMonth((int) $this->employeeId, $day);

        if ($current->isEmpty()) {
            $this->splitRows = [self::BLANK_SPLIT];
            $this->notice = __('salaries.splits.none');

            return;
        }

        $this->employeeName = $current->first()->employee_name ?? $this->employeeName;
        $this->splitRows = $current->map(fn (PayrollSplit $row) => ['budget_line' => $row->budget_line_key, 'percent' => (string) $row->percent])->values()->all();
    }

    public function addSplitRow(): void
    {
        $this->splitRows[] = self::BLANK_SPLIT;
    }

    public function removeSplitRow(int $index): void
    {
        unset($this->splitRows[$index]);
        $this->splitRows = $this->splitRows === [] ? [self::BLANK_SPLIT] : array_values($this->splitRows);
    }

    public function saveSplit(PayrollSplits $splits): void
    {
        $this->resetFeedback();
        $this->guard(ScreenAction::Create);

        $changedOn = $this->day($this->changeDate);

        if ($changedOn === null) {
            $this->failures = [__('salaries.problem.date')];

            return;
        }

        try {
            $from = $splits->replace((int) $this->employeeId, $this->employeeName, $changedOn, array_values($this->splitRows));
        } catch (PayrollException $e) {
            $this->failures = $e->problems;

            return;
        }

        $this->notice = __('salaries.splits.saved', ['date' => $from->toDateString()]);
    }

    public function addLine(): void
    {
        $this->lines[] = self::BLANK_LINE;
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = $this->lines === [] ? [self::BLANK_LINE] : array_values($this->lines);
    }

    /** Fills the lines from the employee's payroll split for the month. */
    public function propose(SalaryAllocation $allocation): void
    {
        $this->resetFeedback();
        $this->guard(ScreenAction::Create);

        $month = CarbonImmutable::createFromFormat('!Y-m', $this->month);

        if (! $month instanceof CarbonImmutable) {
            $this->failures = [__('salaries.problem.month')];

            return;
        }

        try {
            $proposed = $allocation->propose((int) $this->employeeId, $month, $this->amount);
        } catch (PayrollException $e) {
            $this->lines = [self::BLANK_LINE];
            $this->failures = $e->problems;

            return;
        }

        $this->lines = array_map(fn (array $line) => ['budget_line' => $line['budget_line'], 'amount' => $line['amount'], 'percent' => $line['percent']], $proposed);
    }

    public function post(SalaryAllocation $allocation): void
    {
        $this->resetFeedback();
        $user = $this->guard(ScreenAction::Create);

        $date = $this->day($this->date);

        if ($date === null) {
            $this->failures = [__('salaries.problem.date')];

            return;
        }

        if ($this->mode === 'salary' && $this->employeeId !== '' && $this->description === '') {
            $this->description = __('salaries.default_description', ['employee' => $this->employeeName !== '' ? $this->employeeName : $this->employeeId, 'month' => $this->month]);
        }

        try {
            $voucher = $allocation->post(
                $date, $this->description, $this->currency, $this->rate, $this->amount,
                array_values($this->lines), $this->cashAccount, FundSource::tryFrom($this->fundSource) ?? FundSource::DonorGrant,
                (int) $user->getAuthIdentifier(),
            );
        } catch (PayrollException $e) {
            $this->failures = $e->problems;

            return;
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        }

        $this->notice = __('salaries.posted', ['number' => $voucher->id]);
        $this->amount = '';
        $this->description = '';
        $this->lines = [self::BLANK_LINE];
    }

    public function render(SalaryAllocation $allocation): View
    {
        $this->guard(ScreenAction::View);
        $date = $this->day($this->date);

        return view('livewire.salaries', [
            'budgetLines' => GlBudgetLine::query()->orderBy('project_code')->orderBy('key')->get(['key', 'project_code', 'name', 'account_code']),
            'cashAccounts' => GlAccount::query()->where('is_cash', true)->where('is_active', true)->where('currency', $this->currency)->orderBy('code')->get(),
            'fundSources' => FundSource::cases(),
            'canCreate' => auth()->user() instanceof User && auth()->user()->canOnScreen('salaries', ScreenAction::Create),
            'warnings' => $date === null ? [] : $allocation->warnings(array_values($this->lines), $date),
            'total' => $this->linesTotal(),
        ]);
    }

    private function linesTotal(): string
    {
        $sum = '0.00';

        foreach ($this->lines as $line) {
            $clean = str_replace([',', ' '], '', trim($line['amount'] ?? ''));
            $sum = preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? bcadd($sum, Money::normalize($clean), 2) : $sum;
        }

        return $sum;
    }

    private function guard(ScreenAction $action): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canOnScreen('salaries', $action), 403);

        return $user;
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $text);

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failures = [];
    }
}
