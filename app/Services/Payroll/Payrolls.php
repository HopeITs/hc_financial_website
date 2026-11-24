<?php

namespace App\Services\Payroll;

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Models\GlProject;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Approvals\ApprovalChain;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\VoucherDocuments;
use App\Services\Treasury\MonthlyRates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The monthly payroll of a project (finance request of 2026-11-24), the way the payroll summary and payslips are made:
 *
 * - Each employee on the project's staff register gets a line with the basic pay of their contract, an adjustment
 *   (a bonus, or a correction), the unpaid leave and absence (days × basic pay / 30), other deductions and the net pay.
 * - Salaries are fixed in dollars and usually paid in pounds, at the rate the Financial Manager sets for the payroll.
 * - The net paid splits over budget lines by the employee's payroll split of the month (PayrollSplits): the payslip's
 *   "Allocate" lines.
 * - The preparer submits it, the Financial Manager approves it, and it is paid by one allocation voucher (SalaryAllocation)
 *   on the budget lines, which waits for approval like any other.
 */
class Payrolls
{
    /** A month's unpaid day is the basic pay divided by this. */
    public const DAYS_IN_MONTH = 30;

    public const CURRENCIES = ['USD', 'SYP'];

    public function __construct(
        private readonly PayrollSplits $splits,
        private readonly SalaryAllocation $allocation,
        private readonly MonthlyRates $monthly,
        private readonly ApprovalChain $chain,
    ) {}

    /**
     * Adds an employee to the staff register, or changes one.
     *
     * @param  array<string, string>  $form  employee_id, name, position, project, basic_pay, currency
     *
     * @throws PayrollException
     */
    public function saveStaff(?StaffMember $member, array $form): StaffMember
    {
        $problems = [];
        $employeeId = (int) ($form['employee_id'] ?? 0);
        $pay = $this->number((string) ($form['basic_pay'] ?? ''));
        $currency = (string) ($form['currency'] ?? '');

        if ($employeeId <= 0) {
            $problems[] = __('payroll.problem.employee_id');
        } elseif (StaffMember::query()->where('employee_id', $employeeId)->when($member !== null, fn ($query) => $query->whereKeyNot($member->id))->exists()) {
            $problems[] = __('payroll.problem.employee_taken');
        }

        if (trim((string) ($form['name'] ?? '')) === '') {
            $problems[] = __('payroll.problem.name');
        }

        if (! GlProject::query()->whereKey((string) ($form['project'] ?? ''))->exists()) {
            $problems[] = __('payroll.problem.project');
        }

        if ($pay === null || bccomp($pay, '0', 2) !== 1) {
            $problems[] = __('payroll.problem.basic_pay');
        }

        if (! in_array($currency, self::CURRENCIES, true)) {
            $problems[] = __('payroll.problem.currency');
        }

        if ($problems !== []) {
            throw new PayrollException($problems);
        }

        $values = [
            'employee_id' => $employeeId, 'name' => mb_substr(trim((string) $form['name']), 0, 255),
            'position' => trim((string) ($form['position'] ?? '')) ?: null, 'project_code' => (string) $form['project'],
            'basic_pay' => $pay, 'currency' => $currency, 'active' => ($form['active'] ?? '1') !== '0',
        ];

        if ($member === null) {
            return StaffMember::query()->create($values);
        }

        $member->update($values);

        return $member->refresh();
    }

    /**
     * Opens a project's payroll for a month with a line for each active employee on its staff register.
     *
     * @throws PayrollException
     */
    public function open(User $by, string $projectCode, string $month, string $payCurrency): Payroll
    {
        $first = preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? CarbonImmutable::createFromFormat('!Y-m', $month) : false;
        $problems = [];

        if (! $first instanceof CarbonImmutable) {
            $problems[] = __('payroll.problem.month');
        }

        if (! GlProject::query()->whereKey($projectCode)->exists()) {
            $problems[] = __('payroll.problem.project');
        }

        if (! in_array($payCurrency, self::CURRENCIES, true)) {
            $problems[] = __('payroll.problem.currency');
        }

        if ($problems === [] && Payroll::query()->where('project_code', $projectCode)->whereDate('month', $first->toDateString())->exists()) {
            $problems[] = __('payroll.problem.exists');
        }

        if ($problems === [] && ! StaffMember::query()->where('project_code', $projectCode)->where('active', true)->exists()) {
            $problems[] = __('payroll.problem.no_staff');
        }

        if ($problems !== [] || ! $first instanceof CarbonImmutable) {
            throw new PayrollException($problems);
        }

        $rate = $this->monthly->forDay('SYP', $first)['rate'] ?? null;

        return DB::transaction(function () use ($by, $projectCode, $first, $payCurrency, $rate): Payroll {
            $payroll = Payroll::query()->create([
                'project_code' => $projectCode, 'month' => $first->toDateString(), 'pay_currency' => $payCurrency,
                'rate' => $rate, 'status' => Payroll::DRAFT, 'prepared_by' => (int) $by->getAuthIdentifier(),
            ]);
            $this->addStaff($payroll);

            return $payroll->refresh();
        });
    }

    /** Adds a line for each active employee of the project who has none yet (one who joined after the payroll was opened). */
    public function addStaff(Payroll $payroll): int
    {
        $have = $payroll->lines()->pluck('employee_id')->all();
        $added = 0;

        foreach (StaffMember::query()->where('project_code', $payroll->project_code)->where('active', true)->whereNotIn('employee_id', $have)->get() as $member) {
            $line = new PayrollLine([
                'payroll_id' => $payroll->id, 'employee_id' => $member->employee_id, 'name' => $member->name, 'position' => $member->position,
                'currency' => $member->currency, 'basic_pay' => $member->basic_pay,
            ]);
            $this->compute($payroll, $line);
            $line->save();
            $added++;
        }

        return $added;
    }

    /**
     * Changes an employee's adjustment, unpaid days, other deductions and notes; the line and its split are worked out again.
     *
     * @param  array<string, string>  $values
     *
     * @throws PayrollException
     */
    public function updateLine(Payroll $payroll, PayrollLine $line, array $values): PayrollLine
    {
        $this->assertDraft($payroll);
        $adjustment = $this->signed((string) ($values['adjustment'] ?? '0'));
        $days = $this->number((string) ($values['unpaid_days'] ?? '0'));
        $other = $this->number((string) ($values['other_deductions'] ?? '0'));
        $problems = [];

        if ($adjustment === null) {
            $problems[] = __('payroll.problem.adjustment', ['name' => $line->name]);
        }

        if ($days === null || bccomp($days, (string) self::DAYS_IN_MONTH, 2) === 1) {
            $problems[] = __('payroll.problem.unpaid_days', ['name' => $line->name, 'max' => self::DAYS_IN_MONTH]);
        }

        if ($other === null) {
            $problems[] = __('payroll.problem.other_deductions', ['name' => $line->name]);
        }

        if ($problems !== []) {
            throw new PayrollException($problems);
        }

        $line->fill(['adjustment' => $adjustment, 'unpaid_days' => $days, 'other_deductions' => $other, 'notes' => trim((string) ($values['notes'] ?? '')) ?: null]);
        $this->compute($payroll, $line);

        if (bccomp(Money::normalize($line->net), '0', 2) === -1) {
            throw new PayrollException([__('payroll.problem.negative', ['name' => $line->name])]);
        }

        $line->save();

        return $line;
    }

    /** @throws PayrollException */
    public function removeLine(Payroll $payroll, PayrollLine $line): void
    {
        $this->assertDraft($payroll);
        $line->delete();
    }

    /**
     * Sets the rate a dollar salary is paid at in pounds (the Financial Manager's), and works every line out again.
     *
     * @throws PayrollException
     */
    public function setRate(Payroll $payroll, string $rate): Payroll
    {
        if (! in_array($payroll->status, [Payroll::DRAFT, Payroll::SUBMITTED], true)) {
            throw new PayrollException([__('payroll.problem.not_draft')]);
        }

        $value = $this->number($rate, 6);

        if ($value === null || bccomp($value, '0', 6) !== 1) {
            throw new PayrollException([__('payroll.problem.rate')]);
        }

        DB::transaction(function () use ($payroll, $value): void {
            $payroll->update(['rate' => $value]);

            foreach ($payroll->lines()->get() as $line) {
                $this->compute($payroll, $line);
                $line->save();
            }
        });

        return $payroll->refresh();
    }

    /**
     * Totals of the payroll: in the pay currency, and by the contracts' currency.
     *
     * @return array{paid: string, basic: array<string, string>, net: array<string, string>}
     */
    public function totals(Payroll $payroll): array
    {
        $paid = Money::zero();
        $basic = [];
        $net = [];

        foreach ($payroll->lines as $line) {
            $paid = bcadd($paid, Money::normalize($line->net_paid), 2);
            $basic[$line->currency] = bcadd($basic[$line->currency] ?? '0.00', Money::normalize($line->basic_pay), 2);
            $net[$line->currency] = bcadd($net[$line->currency] ?? '0.00', Money::normalize($line->net), 2);
        }

        return ['paid' => $paid, 'basic' => $basic, 'net' => $net];
    }

    /** @throws PayrollException */
    public function submit(Payroll $payroll): Payroll
    {
        $this->assertDraft($payroll);
        $problems = [];
        $lines = $payroll->lines()->get();

        if ($lines->isEmpty()) {
            $problems[] = __('payroll.problem.no_lines');
        }

        if ($this->needsRate($payroll, $lines->pluck('currency')->all()) && bccomp(Money::normalize($payroll->rate ?? '0', 6), '0', 6) !== 1) {
            $problems[] = __('payroll.problem.rate');
        }

        foreach ($lines as $line) {
            if (($line->allocations ?? []) === []) {
                $problems[] = __('payroll.problem.no_split', ['name' => $line->name, 'id' => $line->employee_id]);
            }
        }

        if ($problems !== []) {
            throw new PayrollException($problems);
        }

        $payroll->update(['status' => Payroll::SUBMITTED, 'approvals' => [], 'return_reason' => null]);

        return $payroll->refresh();
    }

    /** The Financial Manager approves the payroll (Head Office stands in); it is routine, so no further step. */
    public function mayApprove(User $user, Payroll $payroll): bool
    {
        return $payroll->status === Payroll::SUBMITTED && $this->chain->mayApproveNext('0.00', [], $user->roleIds());
    }

    /** @throws PayrollException */
    public function approve(User $by, Payroll $payroll): Payroll
    {
        if (! $this->mayApprove($by, $payroll)) {
            throw new PayrollException([__('payroll.problem.not_your_step')]);
        }

        $payroll->update([
            'status' => Payroll::APPROVED, 'approved_at' => now(),
            'approvals' => [['role' => RoleId::FinancialManager->value, 'user' => (int) $by->getAuthIdentifier(), 'name' => $by->name, 'at' => now()->toDateTimeString()]],
        ]);

        return $payroll->refresh();
    }

    /** @throws PayrollException */
    public function returnForChanges(User $by, Payroll $payroll, string $reason): Payroll
    {
        if (! $this->mayApprove($by, $payroll)) {
            throw new PayrollException([__('payroll.problem.not_your_step')]);
        }

        if (trim($reason) === '') {
            throw new PayrollException([__('payroll.problem.return_reason')]);
        }

        $payroll->update(['status' => Payroll::DRAFT, 'approvals' => [], 'return_reason' => trim($reason)]);

        return $payroll->refresh();
    }

    /**
     * Pays an approved payroll: one allocation voucher, the net paid on each budget line from the cash account.
     *
     * @throws PayrollException
     * @throws LedgerRuleException
     */
    public function pay(User $by, Payroll $payroll, string $cashAccount, string $date): Voucher
    {
        if ($payroll->status !== Payroll::APPROVED) {
            throw new PayrollException([__('payroll.problem.not_approved')]);
        }

        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (! $day instanceof CarbonImmutable) {
            throw new PayrollException([__('payroll.problem.date')]);
        }

        $byLine = [];
        foreach ($payroll->lines()->get() as $line) {
            foreach ($line->allocations ?? [] as $share) {
                $byLine[$share['budget_line']] = bcadd($byLine[$share['budget_line']] ?? '0.00', Money::normalize($share['amount']), 2);
            }
        }

        $lines = [];
        foreach ($byLine as $budgetLine => $amount) {
            if (bccomp($amount, '0', 2) === 1) {
                $lines[] = ['budget_line' => (string) $budgetLine, 'amount' => $amount];
            }
        }

        $total = array_reduce($lines, fn (string $sum, array $line): string => bcadd($sum, $line['amount'], 2), Money::zero());
        $voucher = $this->allocation->post(
            $day,
            __('payroll.voucher_description', ['project' => $payroll->project_code, 'month' => $payroll->month->format('m/Y')]),
            $payroll->pay_currency,
            (string) ($payroll->rate ?? ''),
            $total,
            $lines,
            $cashAccount,
            FundSource::DonorGrant,
            (int) $by->getAuthIdentifier(),
        );

        $payroll->update(['status' => Payroll::PAID, 'paid_on' => $day->toDateString(), 'cash_account' => $cashAccount, 'voucher_id' => $voucher->id]);
        // The donor's report lists salaries as payslips (finance request of 2026-11-26).
        app(VoucherDocuments::class)->record($voucher, 'payslip', 'PAY-'.str_pad((string) $payroll->id, 4, '0', STR_PAD_LEFT),
            'Salaries of '.$payroll->project_code.' for '.$payroll->month->format('F Y'));

        return $voucher;
    }

    /** A rejected or deleted payroll voucher puts its payroll back to approved, to be paid again. */
    public function forgetVoucher(Voucher $voucher): void
    {
        Payroll::query()->where('voucher_id', $voucher->id)->update(['status' => Payroll::APPROVED, 'voucher_id' => null, 'paid_on' => null]);
    }

    /** @throws PayrollException */
    public function delete(Payroll $payroll): void
    {
        $this->assertDraft($payroll);
        $payroll->delete();
    }

    /** Works a line out: unpaid leave, net, net paid in the payroll's currency, and its split over budget lines. */
    private function compute(Payroll $payroll, PayrollLine $line): void
    {
        $basic = Money::normalize($line->basic_pay);
        $unpaid = Money::normalize(bcdiv(bcmul($basic, Money::normalize($line->unpaid_days ?? '0'), 6), (string) self::DAYS_IN_MONTH, 6));
        $net = bcsub(bcsub(bcadd($basic, Money::normalize($line->adjustment ?? '0'), 2), $unpaid, 2), Money::normalize($line->other_deductions ?? '0'), 2);
        $rate = Money::normalize($payroll->rate ?? '0', 6);
        $paid = match (true) {
            $line->currency === $payroll->pay_currency => $net,
            bccomp($rate, '0', 6) !== 1 => '0.00',
            $payroll->pay_currency === 'SYP' => Money::normalize(bcmul($net, $rate, 6)),
            default => Money::normalize(bcdiv($net, $rate, 6)),
        };

        $line->unpaid_leave = $unpaid;
        $line->net = $net;
        $line->net_paid = $paid;
        $line->allocations = $this->split($line->employee_id, CarbonImmutable::parse($payroll->month->toDateString()), $paid);
    }

    /**
     * The employee's split of the month, with the share of the amount on each budget line; the last takes the rounding.
     *
     * @return list<array{budget_line: string, name: string, percent: string, amount: string}>
     */
    private function split(int $employeeId, CarbonImmutable $month, string $amount): array
    {
        $rows = $this->splits->forMonth($employeeId, $month);
        $shares = [];
        $given = '0.00';

        foreach ($rows->values() as $index => $row) {
            $share = $index === $rows->count() - 1
                ? bcsub($amount, $given, 2)
                : Money::normalize(bcdiv(bcmul($amount, Money::normalize($row->percent), 6), '100', 6));
            $given = bcadd($given, $share, 2);
            $shares[] = ['budget_line' => $row->budget_line_key, 'name' => (string) ($row->budgetLine?->name ?? $row->budget_line_key), 'percent' => Money::normalize($row->percent), 'amount' => $share];
        }

        return $shares;
    }

    /** @param list<string> $currencies */
    private function needsRate(Payroll $payroll, array $currencies): bool
    {
        return collect($currencies)->contains(fn (string $currency): bool => $currency !== $payroll->pay_currency);
    }

    /** @throws PayrollException */
    private function assertDraft(Payroll $payroll): void
    {
        if ($payroll->status !== Payroll::DRAFT) {
            throw new PayrollException([__('payroll.problem.not_draft')]);
        }
    }

    /** @return numeric-string|null */
    private function number(string $text, int $scale = 2): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));
        $clean = $clean === '' ? '0' : $clean;

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean, $scale) : null;
    }

    /** @return numeric-string|null a number that may be negative (an adjustment that takes away) */
    private function signed(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));
        $negative = str_starts_with($clean, '-');
        $value = $this->number(ltrim($clean, '-'));

        return $value === null ? null : ($negative ? bcsub('0', $value, 2) : $value);
    }
}
