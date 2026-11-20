<?php

namespace App\Services\Excel\Datasets;

use App\Models\MicroInstalment;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use Carbon\CarbonImmutable;
use LogicException;

/** Every micro-loan instalment to Excel, for reading and following up: due, paid, written off and still owed. */
class LoanScheduleExcel extends ExcelDataset
{
    public function screen(): string
    {
        return 'payments';
    }

    public function importable(): bool
    {
        return false;
    }

    public function fileName(): string
    {
        return 'micro-loan-schedule';
    }

    public function columns(): array
    {
        return ['beneficiary', 'project', 'currency', 'due', 'amount', 'paid', 'written_off', 'owed', 'state'];
    }

    public function exportRows(array $context): iterable
    {
        $today = CarbonImmutable::today()->toDateString();
        $instalments = MicroInstalment::query()->with('loan')->join('gl_micro_loans as m', 'm.id', '=', 'gl_micro_instalments.loan_id')
            ->orderBy('m.project_code')->orderBy('m.beneficiary')->orderBy('gl_micro_instalments.due_date')
            ->select('gl_micro_instalments.*')->get();

        foreach ($instalments as $instalment) {
            $owed = $instalment->outstanding();
            $state = match (true) {
                bccomp($owed, '0', 2) <= 0 => (float) ($instalment->written_off_amount ?? 0) > 0 ? 'state_written_off' : 'state_paid',
                $instalment->due_date->toDateString() < $today => 'state_late',
                default => 'state_due',
            };

            yield [
                (string) $instalment->loan?->beneficiary, (string) $instalment->loan?->project_code, (string) $instalment->loan?->currency,
                $instalment->due_date->toDateString(), (float) $instalment->amount, (float) $instalment->paid_amount,
                (float) ($instalment->written_off_amount ?? 0), (float) $owed, __('loans.'.$state),
            ];
        }
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        throw new LogicException('The schedule is not imported.');
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        throw new LogicException('The schedule is not imported.');
    }
}
