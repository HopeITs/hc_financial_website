<?php

namespace App\Livewire;

use App\Enums\RequestStatus;
use App\Enums\ScreenAction;
use App\Models\GlBudgetLine;
use App\Models\LedgerRequest;
use App\Models\RecurringPayment;
use App\Models\User;
use App\Services\Entries\CashVouchers;
use App\Services\Entries\EntryException;
use App\Services\Entries\RecurringPayments;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Recurring payments (finance decision of 2026-11-24): rent and the like, each paid by itself on its day (the daily
 * `payments:recurring` run). The list shows when each is next due, its last voucher and, when it could not be paid,
 * why; a due one can be paid at once.
 */
#[Layout('components.layouts.app')]
class RecurringPaymentsScreen extends Component
{
    /** The payment being changed, `new`, or '' when the form is closed. */
    public string $editing = '';

    /** @var array<string, string> */
    public array $form = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function add(): void
    {
        $this->guard(ScreenAction::Create);
        $this->editing = 'new';
        $this->form = [
            'budget_line' => '', 'payee' => '', 'description' => '', 'currency' => 'USD', 'amount' => '', 'every_months' => '1',
            'next_due' => CarbonImmutable::today()->startOfMonth()->addMonth()->toDateString(), 'ends_on' => '', 'cash_account' => '', 'center' => '', 'request' => '',
        ];
        $this->clearFeedback();
    }

    public function edit(int $id): void
    {
        $this->guard(ScreenAction::Edit);
        $payment = RecurringPayment::query()->findOrFail($id);
        $this->editing = (string) $payment->id;
        $this->form = [
            'budget_line' => $payment->budget_line_key, 'payee' => $payment->payee, 'description' => (string) $payment->description,
            'currency' => $payment->currency, 'amount' => (string) $payment->amount, 'every_months' => (string) $payment->every_months,
            'next_due' => $payment->next_due->toDateString(), 'ends_on' => (string) $payment->ends_on?->toDateString(),
            'cash_account' => $payment->cash_account, 'center' => (string) $payment->center, 'request' => (string) ($payment->request_id ?? ''),
        ];
        $this->clearFeedback();
    }

    public function cancelEdit(): void
    {
        $this->editing = '';
        $this->form = [];
    }

    public function save(RecurringPayments $payments): void
    {
        $this->guard($this->editing === 'new' ? ScreenAction::Create : ScreenAction::Edit);
        $this->clearFeedback();

        try {
            $payments->save($this->user(), $this->editing === 'new' ? null : RecurringPayment::query()->findOrFail((int) $this->editing), $this->form);
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('recurring.saved');
        $this->cancelEdit();
    }

    /** Stops a payment, or starts it again. */
    public function toggle(int $id): void
    {
        $this->guard(ScreenAction::Edit);
        $payment = RecurringPayment::query()->findOrFail($id);
        $payment->update(['active' => ! $payment->active]);
        $this->clearFeedback();
    }

    /** Pays a due payment now instead of waiting for the daily run. */
    public function payNow(RecurringPayments $payments, int $id): void
    {
        abort_unless($this->user()->canOnScreen('single', ScreenAction::Create), 403);
        $this->clearFeedback();
        $payment = RecurringPayment::query()->findOrFail($id);

        if (! $payment->active || $payment->next_due->gt(CarbonImmutable::today())) {
            $this->problems = [__('recurring.problem.not_due')];

            return;
        }

        try {
            $voucher = $payments->pay($payment, $this->user());
        } catch (EntryException $refused) {
            $this->problems = $refused->problems;

            return;
        }

        $this->notice = __('recurring.paid', ['voucher' => $voucher->id]);
    }

    public function render(CashVouchers $vouchers): View
    {
        $user = $this->user();
        abort_unless($user->canOnScreen('recurring', ScreenAction::View), 403);
        $arabic = app()->getLocale() === 'ar';

        return view('livewire.recurring-payments', [
            'payments' => RecurringPayment::query()->with('budgetLine')->orderByDesc('active')->orderBy('next_due')->get(),
            'today' => CarbonImmutable::today(),
            'canCreate' => $user->canOnScreen('recurring', ScreenAction::Create),
            'canEdit' => $user->canOnScreen('recurring', ScreenAction::Edit),
            'canPay' => $user->canOnScreen('single', ScreenAction::Create),
            'budgetLines' => $this->editing === '' ? [] : GlBudgetLine::query()->orderBy('key')->get()->mapWithKeys(fn (GlBudgetLine $line): array => [$line->key => $line->project_code.' · '.$line->name])->all(),
            'cashAccounts' => $this->editing === '' ? [] : $vouchers->cashAccounts()->filter(fn ($account): bool => $account->currency === ($this->form['currency'] ?? 'USD'))->map(fn ($account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en))->all(),
            'requests' => $this->editing === '' ? [] : LedgerRequest::query()->where('status', RequestStatus::Approved->value)->orderByDesc('id')->limit(200)->get()
                ->mapWithKeys(fn (LedgerRequest $request): array => [$request->id => '#'.$request->id.' · '.$request->project_code.' · '.($request->description ?? '')])->all(),
        ]);
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function guard(ScreenAction $action): void
    {
        abort_unless($this->user()->canOnScreen('recurring', $action), 403);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
