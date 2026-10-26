<?php

namespace App\Livewire;

use App\Enums\FundSource;
use App\Enums\ScreenAction;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\MicroLoan;
use App\Models\User;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroLoans as Loans;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Micro project loans: a new beneficiary's loan and grant with the generated schedule, and the instalments received.
 * Both save a voucher waiting for approval (the "Payments (micro loans)" screen of config/navigation.php).
 */
#[Layout('components.layouts.app')]
class MicroLoans extends Component
{
    #[Url]
    public string $tab = 'loans';

    public string $beneficiary = '';

    public string $nationalNumber = '';

    public string $mobile = '';

    public string $partners = '';

    public string $intervalMonths = '1';

    public string $project = '';

    public string $loanAmount = '';

    public string $grantAmount = '';

    public string $currency = 'USD';

    public string $rate = '';

    public string $date = '';

    public string $instalments = '12';

    public string $firstDue = '';

    public string $cashAccount = '';

    public string $budgetLine = '';

    public string $fundSource = 'donor_grant';

    public string $requestId = '';

    public string $loanId = '';

    public string $paymentAmount = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $failures = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->firstDue = now()->addMonth()->toDateString();
    }

    public function open(Loans $loans): void
    {
        $this->resetFeedback();
        $user = $this->guard(ScreenAction::Create);

        $date = $this->day($this->date);
        $firstDue = $this->day($this->firstDue);

        if ($date === null || $firstDue === null) {
            $this->failures = [__('loans.problem.date')];

            return;
        }

        try {
            $result = $loans->open(
                $this->beneficiary, $this->project, $this->loanAmount, $this->grantAmount, $this->currency, $this->rate, $date,
                (int) $this->instalments, $firstDue, $this->cashAccount, $this->budgetLine,
                FundSource::tryFrom($this->fundSource) ?? FundSource::DonorGrant, (int) $user->getAuthIdentifier(),
                ctype_digit($this->requestId) ? (int) $this->requestId : null,
                $this->nationalNumber, $this->mobile, $this->partners, (int) $this->intervalMonths,
            );
        } catch (LoanException $e) {
            $this->failures = $e->problems;

            return;
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        }

        $this->notice = __('loans.opened', ['number' => $result['voucher']->id]);
        $this->beneficiary = $this->loanAmount = $this->grantAmount = $this->nationalNumber = $this->mobile = $this->partners = '';
    }

    public function receive(Loans $loans): void
    {
        $this->resetFeedback();
        $user = $this->guard(ScreenAction::Create);

        $date = $this->day($this->date);
        $loan = MicroLoan::query()->find((int) $this->loanId);

        if ($date === null || $loan === null) {
            $this->failures = [$date === null ? __('loans.problem.date') : __('loans.problem.loan')];

            return;
        }

        try {
            $voucher = $loans->receive($loan, $date, $this->paymentAmount, $this->rate, $this->cashAccount, (int) $user->getAuthIdentifier());
        } catch (LoanException $e) {
            $this->failures = $e->problems;

            return;
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        }

        $this->notice = __('loans.received', ['number' => $voucher->id]);
        $this->paymentAmount = '';
    }

    public function render(Loans $loans): View
    {
        $this->guard(ScreenAction::View);
        $today = CarbonImmutable::today();
        $currency = $this->currency;

        if ($this->tab === 'payments' && ($loan = MicroLoan::query()->find((int) $this->loanId)) !== null) {
            $currency = $loan->currency;
        }

        $rows = MicroLoan::query()->with('instalments')->orderBy('project_code')->orderBy('beneficiary')->orderBy('id')->get()
            ->map(function (MicroLoan $loan) use ($loans, $today): array {
                $late = $loans->latePayments($loan, $today);

                return ['loan' => $loan, 'arrears' => $loans->arrears($loan, $today), 'late' => $late, 'flagged' => $late >= Loans::LATE_FLAG];
            });

        return view('livewire.micro-loans', [
            'rows' => $rows,
            'projects' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name']),
            'budgetLines' => GlBudgetLine::query()->when($this->project !== '', fn ($query) => $query->where('project_code', $this->project))->orderBy('key')->get(['key', 'name', 'account_code']),
            'cashAccounts' => GlAccount::query()->where('is_cash', true)->where('is_active', true)->where('currency', $currency)->orderBy('code')->get(),
            'fundSources' => FundSource::cases(),
            'canCreate' => auth()->user() instanceof User && auth()->user()->canOnScreen('payments', ScreenAction::Create),
        ]);
    }

    private function guard(ScreenAction $action): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canOnScreen('payments', $action), 403);

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
