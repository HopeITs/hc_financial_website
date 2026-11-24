<?php

namespace App\Services\Excel\Datasets;

use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\Payrolls;

/**
 * A payroll's monthly changes to and from Excel (context `payroll`, finance request of 2026-11-25): one employee per
 * row, by employee ID, with the adjustment or bonus, the unpaid days, the other deductions and the notes. Only a payroll
 * being prepared takes them; the name is there to read, not imported. The rules are the payroll's (Payrolls::updateLine).
 */
class PayrollLinesExcel extends ExcelDataset
{
    public function __construct(private readonly Payrolls $payrolls) {}

    public function screen(): string
    {
        return 'payroll';
    }

    public function importAction(): string
    {
        return 'edit';
    }

    public function fileName(): string
    {
        return 'payroll';
    }

    public function columns(): array
    {
        return ['employee_id', 'name', 'adjustment', 'unpaid_days', 'other_deductions', 'notes'];
    }

    public function exportColumns(): array
    {
        return [...$this->columns(), 'basic_pay', 'net'];
    }

    public function exportRows(array $context): iterable
    {
        foreach ($this->payroll($context)?->lines()->get() ?? [] as $line) {
            yield [$line->employee_id, $line->name, (float) $line->adjustment, (float) $line->unpaid_days, (float) $line->other_deductions, (string) $line->notes, (float) $line->basic_pay, (float) $line->net];
        }
    }

    public function exampleRows(array $context): array
    {
        $rows = [];
        foreach ($this->payroll($context)?->lines()->get() ?? [] as $line) {
            $rows[] = [$line->employee_id, $line->name, 0, 0, 0, ''];
        }

        return $rows;
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $payroll = $this->payroll($context);
        $problems = [];

        if ($payroll === null || $payroll->status !== Payroll::DRAFT) {
            return ['action' => 'update', 'problems' => [__('payroll.problem.not_draft')]];
        }

        $line = $this->line($payroll, $cells['employee_id']);

        if ($line === null) {
            $problems[] = __('payroll.problem.not_on_payroll', ['id' => $cells['employee_id']]);
        }

        if (! $this->number($cells['adjustment'], true)) {
            $problems[] = __('payroll.problem.adjustment', ['name' => $cells['name'] ?: $cells['employee_id']]);
        }

        if (! $this->number($cells['unpaid_days']) || (float) $cells['unpaid_days'] > Payrolls::DAYS_IN_MONTH) {
            $problems[] = __('payroll.problem.unpaid_days', ['name' => $cells['name'] ?: $cells['employee_id'], 'max' => Payrolls::DAYS_IN_MONTH]);
        }

        if (! $this->number($cells['other_deductions'])) {
            $problems[] = __('payroll.problem.other_deductions', ['name' => $cells['name'] ?: $cells['employee_id']]);
        }

        if (in_array($cells['employee_id'], array_column($before, 'employee_id'), true)) {
            $problems[] = __('excel.twice');
        }

        // The net pay cannot go below zero, as on the screen.
        if ($problems === [] && $line !== null) {
            $basic = (float) $line->basic_pay;
            $net = $basic + (float) str_replace(',', '', $cells['adjustment'] ?: '0')
                - $basic * (float) str_replace(',', '', $cells['unpaid_days'] ?: '0') / Payrolls::DAYS_IN_MONTH
                - (float) str_replace(',', '', $cells['other_deductions'] ?: '0');

            if ($net < 0) {
                $problems[] = __('payroll.problem.negative', ['name' => $line->name]);
            }
        }

        return ['action' => 'update', 'problems' => $problems];
    }

    /** @throws PayrollException */
    protected function saveRow(array $cells, User $user, array $context): void
    {
        $payroll = $this->payroll($context);
        $line = $payroll !== null ? $this->line($payroll, $cells['employee_id']) : null;

        if ($payroll === null || $line === null) {
            return;
        }

        $this->payrolls->updateLine($payroll, $line, [
            'adjustment' => $cells['adjustment'] === '' ? '0' : $cells['adjustment'], 'unpaid_days' => $cells['unpaid_days'] === '' ? '0' : $cells['unpaid_days'],
            'other_deductions' => $cells['other_deductions'] === '' ? '0' : $cells['other_deductions'], 'notes' => $cells['notes'],
        ]);
    }

    /** @param array<string, mixed> $context */
    private function payroll(array $context): ?Payroll
    {
        return isset($context['payroll']) ? Payroll::query()->find((int) $context['payroll']) : null;
    }

    private function line(Payroll $payroll, string $employeeId): ?PayrollLine
    {
        return ctype_digit($employeeId) ? PayrollLine::query()->where('payroll_id', $payroll->id)->where('employee_id', (int) $employeeId)->first() : null;
    }

    /** Empty, or a number (negative only when allowed). */
    private function number(string $text, bool $signed = false): bool
    {
        $clean = str_replace([',', ' '], '', $text);

        return $clean === '' || preg_match($signed ? '/^-?\d+(\.\d+)?$/' : '/^\d+(\.\d+)?$/', $clean) === 1;
    }
}
