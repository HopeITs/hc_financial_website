<?php

namespace App\Services\Payroll;

use App\Models\GlBudgetLine;
use App\Models\PayrollSplit;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The split of an employee's salary over projects and budget lines, kept with the monthly payroll. A split is valid from
 * the first day of a month and applies to whole months only: a change made during a month starts the month after
 * (decision R2-6, no day-based proration). The percentages of one employee and one start date add up to 100.
 */
class PayrollSplits
{
    /** The first day of the month a change made on this day takes effect: the same day when it is a 1st, else the next 1st. */
    public function effectiveFrom(CarbonImmutable $date): CarbonImmutable
    {
        return $date->day === 1 ? $date->startOfDay() : $date->startOfMonth()->addMonth();
    }

    /**
     * The split in force for the month: the newest one that started on or before it. Empty when the employee has none.
     *
     * @return Collection<int, PayrollSplit>
     */
    public function forMonth(int $employeeId, CarbonImmutable $month): Collection
    {
        $from = PayrollSplit::query()
            ->where('legacy_employee_id', $employeeId)
            ->whereDate('valid_from', '<=', $month->startOfMonth()->toDateString())
            ->max('valid_from');

        if ($from === null) {
            return new Collection;
        }

        return PayrollSplit::query()
            ->with(['project', 'budgetLine'])
            ->where('legacy_employee_id', $employeeId)
            ->whereDate('valid_from', CarbonImmutable::parse((string) $from)->toDateString())
            ->orderBy('id')
            ->get();
    }

    /**
     * Replaces the split an employee has from the effective date with the given rows, or creates it.
     *
     * @param  list<array<string, string>>  $rows
     * @return CarbonImmutable the date the split starts
     *
     * @throws PayrollException
     */
    public function replace(int $employeeId, ?string $name, CarbonImmutable $changedOn, array $rows): CarbonImmutable
    {
        $from = $this->effectiveFrom($changedOn);
        $problems = [];

        if ($employeeId <= 0) {
            $problems[] = __('salaries.problem.employee');
        }

        $rows = array_values(array_filter($rows, fn (array $row) => trim($row['budget_line'] ?? '') !== '' || trim($row['percent'] ?? '') !== ''));

        if ($rows === []) {
            $problems[] = __('salaries.problem.no_rows');
        }

        $lines = GlBudgetLine::query()->whereIn('key', array_column($rows, 'budget_line'))->get()->keyBy('key');
        $sum = '0.00';
        $seen = [];
        $clean = [];

        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $key = trim($row['budget_line'] ?? '');
            $percent = str_replace([',', ' '], '', trim($row['percent'] ?? ''));

            if (! $lines->has($key)) {
                $problems[] = __('salaries.problem.budget_line', ['line' => $number]);

                continue;
            }

            if (isset($seen[$key])) {
                $problems[] = __('salaries.problem.duplicate', ['line' => $number]);

                continue;
            }
            $seen[$key] = true;

            $value = preg_match('/^\d{1,3}(\.\d{1,2})?$/', $percent) === 1 ? Money::normalize($percent) : null;

            if ($value === null || bccomp($value, '0', 2) <= 0 || bccomp($value, '100', 2) > 0) {
                $problems[] = __('salaries.problem.percent', ['line' => $number]);

                continue;
            }

            $sum = bcadd($sum, $value, 2);
            $clean[] = ['budget_line' => $lines[$key], 'percent' => $value];
        }

        if ($problems === [] && bccomp($sum, '100', 2) !== 0) {
            $problems[] = __('salaries.problem.sum', ['sum' => $sum]);
        }

        if ($problems !== []) {
            throw new PayrollException($problems);
        }

        DB::transaction(function () use ($employeeId, $name, $from, $clean) {
            PayrollSplit::query()->where('legacy_employee_id', $employeeId)->whereDate('valid_from', $from->toDateString())->delete();

            foreach ($clean as $row) {
                PayrollSplit::query()->create([
                    'legacy_employee_id' => $employeeId,
                    'employee_name' => $name !== null && trim($name) !== '' ? trim($name) : null,
                    'project_code' => $row['budget_line']->project_code,
                    'budget_line_key' => $row['budget_line']->key,
                    'percent' => $row['percent'],
                    'valid_from' => $from->toDateString(),
                ]);
            }
        });

        return $from;
    }
}
