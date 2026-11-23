<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Livewire\Concerns\HasFormDraft;
use App\Models\GlAccount;
use App\Models\MicroLoan;
use App\Models\User;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroLoans as Loans;
use App\Services\Treasury\DailyRates;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Micro project loans: the instalments received, and each loan's card (schedule, payments, reschedule, pay off, write
 * off). A loan opens from its micro-project file when the beneficiary signs the delivery protocol (MicroProjectFiles);
 * it is no longer typed here (finance decisions of 2026-11-23).
 */
#[Layout('components.layouts.app')]
class MicroLoans extends Component
{
    use HasFormDraft;

    public string $date = '';

    public string $cashAccount = '';

    public string $loanId = '';

    public string $paymentAmount = '';

    /** The loan whose card is open, by id ('' for none). */
    #[Url]
    public string $card = '';

    public string $cardBeneficiary = '';

    public string $cardNationalNumber = '';

    public string $cardMobile = '';

    public string $cardPartners = '';

    public string $rescheduleCount = '';

    public string $rescheduleFirst = '';

    public string $rescheduleInterval = '1';

    public string $writeOffReason = '';

    public string $deleteReason = '';

    public ?string $notice = null;

    /** @var list<string> */
    public array $failures = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function receive(Loans $loans): void
    {
        $this->resetFeedback();
        $user = $this->guard(ScreenAction::Create);

        $date = $this->day($this->date);
        $loan = MicroLoan::query()->active()->find((int) $this->loanId);

        if ($date === null || $loan === null) {
            $this->failures = [$date === null ? __('loans.problem.date') : __('loans.problem.loan')];

            return;
        }

        try {
            $voucher = $loans->receive($loan, $date, $this->paymentAmount, $this->cashAccount, (int) $user->getAuthIdentifier());
        } catch (LoanException $e) {
            $this->failures = $e->problems;

            return;
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        }

        $this->forgetDraft();
        $this->notice = __('loans.received', ['number' => $voucher->id]);
        $this->paymentAmount = '';
    }

    /** Opens a loan's card: its schedule, payments and the beneficiary's details. */
    public function openCard(int $id): void
    {
        $this->guard(ScreenAction::View);
        $loan = MicroLoan::query()->findOrFail($id);
        $this->card = (string) $loan->id;
        $this->cardBeneficiary = $loan->beneficiary;
        $this->cardNationalNumber = (string) $loan->national_number;
        $this->cardMobile = (string) $loan->mobile;
        $this->cardPartners = (string) $loan->partners;
        $this->rescheduleCount = (string) max(1, $loan->instalments()->get()->filter(fn ($instalment) => bccomp($instalment->outstanding(), '0', 2) > 0)->count());
        $this->rescheduleFirst = CarbonImmutable::today()->addMonthNoOverflow()->toDateString();
        $this->rescheduleInterval = (string) $loan->payment_interval_months;
        $this->reset('writeOffReason', 'deleteReason');
        $this->resetFeedback();
    }

    public function closeCard(): void
    {
        $this->card = '';
    }

    public function saveDetails(Loans $loans): void
    {
        $this->resetFeedback();
        $this->guard(ScreenAction::Create);

        try {
            $loans->updateDetails($this->cardLoan(), $this->cardBeneficiary, $this->cardNationalNumber, $this->cardMobile, $this->cardPartners);
        } catch (LoanException $e) {
            $this->failures = $e->problems;

            return;
        }

        $this->notice = __('loans.details_saved');
    }

    /** On the payments tab: the amount becomes everything the chosen loan still owes. */
    public function fillOwed(Loans $loans): void
    {
        $loan = MicroLoan::query()->active()->find((int) $this->loanId);
        if ($loan !== null) {
            $this->paymentAmount = $loans->payOffAmount($loan);
        }
    }

    /** Fills the payment with everything still owed, on the payments tab. */
    public function payOff(Loans $loans): void
    {
        $this->guard(ScreenAction::Create);
        $loan = $this->cardLoan();
        $this->loanId = (string) $loan->id;
        $this->paymentAmount = $loans->payOffAmount($loan);
        $this->date = CarbonImmutable::today()->toDateString();
        $this->card = '';
        $this->resetFeedback();
        $this->notice = __('loans.pay_off_filled', ['amount' => number_format((float) $this->paymentAmount, 2), 'currency' => $loan->currency]);
    }

    public function reschedule(Loans $loans): void
    {
        $this->resetFeedback();
        $this->guard(ScreenAction::Create);
        $first = $this->day($this->rescheduleFirst);

        if ($first === null) {
            $this->failures = [__('loans.problem.date')];

            return;
        }

        try {
            $loans->reschedule($this->cardLoan(), (int) $this->rescheduleCount, $first, (int) $this->rescheduleInterval);
        } catch (LoanException $e) {
            $this->failures = $e->problems;

            return;
        }

        $this->notice = __('loans.rescheduled');
    }

    public function writeOff(Loans $loans): void
    {
        $this->resetFeedback();
        $user = $this->guard(ScreenAction::Create);

        try {
            $voucher = $loans->writeOff($this->cardLoan(), CarbonImmutable::today(), $this->writeOffReason, (int) $user->getAuthIdentifier());
        } catch (LoanException $e) {
            $this->failures = $e->problems;

            return;
        } catch (LedgerRuleException $e) {
            $this->failures = array_map(fn ($violation) => $violation->message, $e->violations);

            return;
        }

        $this->writeOffReason = '';
        $this->notice = __('loans.written_off_notice', ['number' => $voucher->id]);
    }

    public function deleteLoan(Loans $loans): void
    {
        $this->resetFeedback();
        $user = $this->guard(ScreenAction::Create);

        try {
            $loans->delete($this->cardLoan(), $user, $this->deleteReason);
        } catch (LoanException $e) {
            $this->failures = $e->problems;

            return;
        }

        $this->card = '';
        $this->deleteReason = '';
        $this->notice = __('loans.deleted');
    }

    /** The Excel tools saved loans: show them with their message. */
    #[On('excel-imported')]
    public function excelImported(string $message): void
    {
        $this->resetFeedback();
        $this->notice = $message;
    }

    private function cardLoan(): MicroLoan
    {
        return MicroLoan::query()->findOrFail((int) $this->card);
    }

    protected function draftFields(): array
    {
        return ['date', 'cashAccount', 'loanId', 'paymentAmount'];
    }

    protected function draftScreen(): string
    {
        return 'payments';
    }

    protected function draftPermission(): array
    {
        return ['payments', 'create'];
    }

    public function render(Loans $loans): View
    {
        $this->guard(ScreenAction::View);
        $today = CarbonImmutable::today();

        $rows = MicroLoan::query()->active()->with('instalments')->orderBy('project_code')->orderBy('beneficiary')->orderBy('id')->get()
            ->map(function (MicroLoan $loan) use ($loans, $today): array {
                $late = $loans->latePayments($loan, $today);

                return ['loan' => $loan, 'arrears' => $loans->arrears($loan, $today), 'late' => $late, 'flagged' => $late >= Loans::LATE_FLAG];
            });

        $paymentLoan = MicroLoan::query()->active()->find((int) $this->loanId);
        $paymentDay = $this->day($this->date) ?? $today;
        $daily = $paymentLoan !== null && $paymentLoan->currency !== 'USD' ? app(DailyRates::class)->rateFor($paymentLoan->currency, $paymentDay) : null;
        $cardLoan = $this->card !== '' ? MicroLoan::query()->with(['instalments', 'payments.voucher', 'disbursement'])->find((int) $this->card) : null;

        return view('livewire.micro-loans', [
            'dailyRate' => $paymentLoan === null || $paymentLoan->currency === 'USD' ? null : [
                'currency' => $paymentLoan->currency,
                'rate' => $daily !== null ? rtrim(rtrim((string) $daily->rate, '0'), '.') : null,
                'day' => $daily?->day->format('d/m/Y'),
            ],
            'cardLoan' => $cardLoan,
            'cardOwed' => $cardLoan?->owed(),
            'cardArrears' => $cardArrears = ($cardLoan !== null ? $loans->arrears($cardLoan, $today) : null),
            'cardFigures' => $cardLoan === null ? null : [
                'loan' => number_format((float) $cardLoan->loan_amount, 2),
                'grant' => number_format((float) $cardLoan->grant_amount, 2),
                'owed' => number_format((float) $cardLoan->owed(), 2),
                'arrears' => number_format((float) ($cardArrears['amount'] ?? 0), 2),
            ],
            'today' => $today,
            'rows' => $rows,
            // An instalment is received in the loan's currency.
            'cashAccounts' => GlAccount::query()->where('is_cash', true)->where('is_active', true)
                ->where('currency', $paymentLoan->currency ?? 'USD')->orderBy('code')->get(),
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
