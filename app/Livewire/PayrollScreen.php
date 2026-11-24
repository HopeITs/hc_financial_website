<?php

namespace App\Livewire;

use App\Enums\ScreenAction;
use App\Models\GlProject;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Entries\CashVouchers;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\Payrolls;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The monthly payroll (finance request of 2026-11-24): the staff register with each contract's salary, and each
 * project's payroll for a month: absences and unpaid leave, bonuses and other deductions, the Financial Manager's rate
 * and approval, the payment voucher, and the payroll summary and payslips to print.
 */
#[Layout('components.layouts.app')]
class PayrollScreen extends Component
{
    /** `payrolls` or `staff`. */
    #[Url]
    public string $tab = 'payrolls';

    /** The open payroll's id, or ''. */
    #[Url(as: 'payroll')]
    public string $open = '';

    /** @var array<string, string> the form of a new payroll */
    public array $start = [];

    /** @var array<int|string, array<string, string>> each line's adjustment, unpaid days, other deductions and notes */
    public array $edits = [];

    public string $rate = '';

    public string $returnReason = '';

    public string $payAccount = '';

    public string $payDate = '';

    /** The staff member being changed, `new`, or ''. */
    public string $staffEditing = '';

    /** @var array<string, string> */
    public array $staff = [];

    public ?string $notice = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        $this->start = ['project' => '', 'month' => CarbonImmutable::today()->format('Y-m'), 'currency' => 'SYP'];
        $this->payDate = CarbonImmutable::today()->toDateString();

        $found = ctype_digit($this->open) ? Payroll::query()->find((int) $this->open) : null;
        if ($found !== null) {
            $this->load($found);
        } else {
            $this->open = '';
        }
    }

    public function openPayroll(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Create);

        $this->attempt(function () use ($payrolls): void {
            $payroll = $payrolls->open($this->user(), $this->start['project'] ?? '', $this->start['month'] ?? '', $this->start['currency'] ?? '');
            $this->load($payroll);
            $this->notice = __('payroll.opened');
        });
    }

    public function select(int $id): void
    {
        $this->clearFeedback();
        $this->load(Payroll::query()->findOrFail($id));
    }

    public function closePayroll(): void
    {
        $this->open = '';
        $this->edits = [];
        $this->clearFeedback();
    }

    /** Saves every line's changes. */
    public function saveLines(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($payrolls): void {
            $payroll = $this->current();
            $problems = [];

            foreach ($payroll->lines()->get() as $line) {
                try {
                    $payrolls->updateLine($payroll, $line, $this->edits[$line->id] ?? []);
                } catch (PayrollException $refused) {
                    $problems = [...$problems, ...$refused->problems];
                }
            }

            if ($problems !== []) {
                throw new PayrollException($problems);
            }

            $this->load($payroll);
            $this->notice = __('payroll.saved');
        });
    }

    public function addStaff(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Edit);
        $added = $payrolls->addStaff($this->current());
        $this->load($this->current());
        $this->notice = trans_choice('payroll.staff_added', $added, ['count' => $added]);
    }

    public function removeLine(Payrolls $payrolls, int $lineId): void
    {
        $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($payrolls, $lineId): void {
            $payroll = $this->current();
            $payrolls->removeLine($payroll, PayrollLine::query()->where('payroll_id', $payroll->id)->findOrFail($lineId));
            $this->load($payroll);
        });
    }

    public function applyRate(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($payrolls): void {
            $this->load($payrolls->setRate($this->current(), $this->rate));
            $this->notice = __('payroll.rate_set');
        });
    }

    public function submit(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Edit);

        $this->attempt(function () use ($payrolls): void {
            $this->load($payrolls->submit($this->current()));
            $this->notice = __('payroll.submitted');
        });
    }

    public function approve(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Approve);

        $this->attempt(function () use ($payrolls): void {
            $this->load($payrolls->approve($this->user(), $this->current()));
            $this->notice = __('payroll.approved');
        });
    }

    public function returnPayroll(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Approve);

        $this->attempt(function () use ($payrolls): void {
            $this->load($payrolls->returnForChanges($this->user(), $this->current(), $this->returnReason));
            $this->notice = __('payroll.returned');
        });
    }

    public function pay(Payrolls $payrolls): void
    {
        abort_unless($this->user()->canOnScreen('payroll', ScreenAction::Create), 403);

        $this->attempt(function () use ($payrolls): void {
            $voucher = $payrolls->pay($this->user(), $this->current(), $this->payAccount, $this->payDate);
            $this->load($this->current());
            $this->notice = __('payroll.paid', ['voucher' => $voucher->id]);
        });
    }

    public function deletePayroll(Payrolls $payrolls): void
    {
        $this->guard(ScreenAction::Delete);

        $this->attempt(function () use ($payrolls): void {
            $payrolls->delete($this->current());
            $this->open = '';
            $this->notice = __('payroll.deleted');
        });
    }

    public function newStaff(): void
    {
        $this->guard(ScreenAction::Create);
        $this->staffEditing = 'new';
        $this->staff = ['employee_id' => '', 'name' => '', 'position' => '', 'project' => '', 'basic_pay' => '', 'currency' => 'USD', 'active' => '1'];
        $this->clearFeedback();
    }

    public function editStaff(int $id): void
    {
        $this->guard(ScreenAction::Edit);
        $member = StaffMember::query()->findOrFail($id);
        $this->staffEditing = (string) $member->id;
        $this->staff = [
            'employee_id' => (string) $member->employee_id, 'name' => $member->name, 'position' => (string) $member->position,
            'project' => $member->project_code, 'basic_pay' => (string) $member->basic_pay, 'currency' => $member->currency, 'active' => $member->active ? '1' : '0',
        ];
        $this->clearFeedback();
    }

    public function saveStaff(Payrolls $payrolls): void
    {
        $this->guard($this->staffEditing === 'new' ? ScreenAction::Create : ScreenAction::Edit);

        $this->attempt(function () use ($payrolls): void {
            $payrolls->saveStaff($this->staffEditing === 'new' ? null : StaffMember::query()->findOrFail((int) $this->staffEditing), $this->staff);
            $this->staffEditing = '';
            $this->notice = __('payroll.staff_saved');
        });
    }

    public function cancelStaff(): void
    {
        $this->staffEditing = '';
    }

    public function render(Payrolls $payrolls, CashVouchers $vouchers): View
    {
        $user = $this->user();
        abort_unless($user->canOnScreen('payroll', ScreenAction::View), 403);
        $current = ctype_digit($this->open) ? Payroll::query()->with(['lines', 'project'])->find((int) $this->open) : null;
        $arabic = app()->getLocale() === 'ar';

        return view('livewire.payroll', [
            'payrolls' => Payroll::query()->with('project')->orderByDesc('month')->orderBy('project_code')->limit(120)->get(),
            'current' => $current,
            'totals' => $current !== null ? $payrolls->totals($current) : null,
            'projects' => GlProject::query()->orderBy('code')->get()->mapWithKeys(fn (GlProject $project): array => [$project->code => $project->code.' · '.$project->name])->all(),
            'members' => $this->tab === 'staff' ? StaffMember::query()->orderByDesc('active')->orderBy('project_code')->orderBy('name')->get() : collect(),
            'canCreate' => $user->canOnScreen('payroll', ScreenAction::Create),
            'canEdit' => $user->canOnScreen('payroll', ScreenAction::Edit),
            'canDelete' => $user->canOnScreen('payroll', ScreenAction::Delete),
            'mayApprove' => $current !== null && $user->canOnScreen('payroll', ScreenAction::Approve) && $payrolls->mayApprove($user, $current),
            'cashAccounts' => $current !== null && $current->status === Payroll::APPROVED
                ? $vouchers->cashAccounts()->filter(fn ($account): bool => $account->currency === $current->pay_currency)->map(fn ($account): string => $account->code.' · '.($arabic ? $account->name_ar : $account->name_en))->all()
                : [],
        ]);
    }

    private function load(Payroll $payroll): void
    {
        $this->open = (string) $payroll->id;
        $this->rate = $payroll->rate !== null ? rtrim(rtrim((string) $payroll->rate, '0'), '.') : '';
        $this->returnReason = '';
        $this->edits = $payroll->lines()->get()->mapWithKeys(fn (PayrollLine $line): array => [$line->id => [
            'adjustment' => $this->plain($line->adjustment), 'unpaid_days' => $this->plain($line->unpaid_days),
            'other_deductions' => $this->plain($line->other_deductions), 'notes' => (string) $line->notes,
        ]])->all();
    }

    /** "0" for zero, "12.5" for 12.50: easier to type over. */
    private function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    /** Runs an action, showing the rules' refusals instead of failing. */
    private function attempt(callable $action): void
    {
        $this->clearFeedback();

        try {
            $action();
        } catch (PayrollException $refused) {
            $this->problems = $refused->problems;
        } catch (LedgerRuleException $refused) {
            $this->problems = array_map(fn ($violation): string => $violation->message, $refused->violations);
        }
    }

    private function current(): Payroll
    {
        return Payroll::query()->findOrFail((int) $this->open);
    }

    private function clearFeedback(): void
    {
        $this->notice = null;
        $this->problems = [];
    }

    private function guard(ScreenAction $action): void
    {
        abort_unless($this->user()->canOnScreen('payroll', $action), 403);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
