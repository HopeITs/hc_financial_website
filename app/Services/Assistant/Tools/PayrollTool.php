<?php

namespace App\Services\Assistant\Tools;

use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\User;
use App\Services\Payroll\Payrolls;
use Illuminate\Support\Facades\Schema;

/**
 * The monthly payrolls, with their totals and, for one project and month, each employee's line.
 */
class PayrollTool extends Tool
{
    public function __construct(private readonly Payrolls $payrolls) {}

    public function name(): string
    {
        return 'payrolls';
    }

    public function allows(User $user): bool
    {
        return $this->can($user, 'payroll');
    }

    public function definition(): array
    {
        return [
            'description' => 'Monthly payrolls, newest first: project, month, pay currency, the Financial Manager\'s rate, status (draft, submitted, approved, paid), number of employees, totals of basic pay and net by contract currency, the total paid in the pay currency, and the payment voucher. With include_lines: each employee\'s basic pay, bonus/adjustment, unpaid days, other deductions, net and net paid.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'month' => ['type' => 'string', 'description' => 'YYYY-MM.'],
                    'project' => ['type' => 'string', 'description' => 'Exact project code.'],
                    'status' => ['type' => 'string', 'enum' => Payroll::STATUSES],
                    'include_lines' => ['type' => 'boolean', 'description' => 'Also return each employee\'s line.'],
                ],
            ],
        ];
    }

    public function run(User $user, array $input, string $conversationId): array
    {
        if (! Schema::hasTable('gl_payrolls')) {
            return ['payrolls' => []];
        }

        $month = $this->text($input, 'month', 7);
        $project = $this->text($input, 'project', 40);
        $status = $this->text($input, 'status', 20);
        $limit = $this->projectLimit($user);
        $withLines = $this->flag($input, 'include_lines');

        $payrolls = Payroll::query()->with('lines')
            ->when($limit !== null, fn ($query) => $query->whereIn('project_code', $limit ?? []))
            ->when(preg_match('/^\d{4}-\d{2}$/', $month) === 1, fn ($query) => $query->whereDate('month', '>=', $month.'-01')->whereDate('month', '<=', $month.'-31'))
            ->when($project !== '', fn ($query) => $query->where('project_code', $project))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('month')->orderBy('project_code')
            ->limit(20)
            ->get();

        return ['payrolls' => $payrolls->map(function (Payroll $payroll) use ($withLines): array {
            $totals = $this->payrolls->totals($payroll);
            $row = [
                'project' => $payroll->project_code,
                'month' => $payroll->month->format('Y-m'),
                'pay_currency' => $payroll->pay_currency,
                'rate' => $payroll->rate,
                'status' => $payroll->status,
                'employees' => $payroll->lines->count(),
                'basic_by_currency' => $totals['basic'],
                'net_by_currency' => $totals['net'],
                'total_paid' => $totals['paid'],
                'return_reason' => $payroll->return_reason,
                'paid_on' => $payroll->paid_on?->toDateString(),
                'voucher' => $payroll->voucher_id,
            ];

            if ($withLines) {
                $row['lines'] = $payroll->lines->take($this->maxRows())->map(fn (PayrollLine $line): array => [
                    'employee_id' => $line->employee_id, 'name' => $line->name, 'position' => $line->position, 'currency' => $line->currency,
                    'basic_pay' => $line->basic_pay, 'adjustment' => $line->adjustment, 'unpaid_days' => $line->unpaid_days,
                    'unpaid_leave' => $line->unpaid_leave, 'other_deductions' => $line->other_deductions, 'net' => $line->net, 'net_paid' => $line->net_paid,
                    'notes' => $line->notes,
                ])->values()->all();
            }

            return $row;
        })->values()->all()];
    }
}
