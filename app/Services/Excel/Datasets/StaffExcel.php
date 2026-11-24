<?php

namespace App\Services\Excel\Datasets;

use App\Models\GlProject;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\Payrolls;
use Illuminate\Support\Facades\Gate;

/**
 * The staff register to and from Excel (finance request of 2026-11-25): one employee per row, known by their employee
 * ID. An ID already on the register changes that employee (the person needs `edit`); a new ID adds one. The rules are
 * the payroll's (Payrolls::saveStaff).
 */
class StaffExcel extends ExcelDataset
{
    private const YES = ['1', 'yes', 'y', 'true', 'active', 'نعم'];

    private const NO = ['0', 'no', 'n', 'false', 'inactive', 'لا'];

    public function __construct(private readonly Payrolls $payrolls) {}

    public function screen(): string
    {
        return 'payroll';
    }

    public function fileName(): string
    {
        return 'staff';
    }

    public function columns(): array
    {
        return ['employee_id', 'name', 'position', 'project', 'basic_pay', 'currency', 'active'];
    }

    public function exportRows(array $context): iterable
    {
        foreach (StaffMember::query()->orderBy('project_code')->orderBy('name')->cursor() as $member) {
            yield [$member->employee_id, $member->name, (string) $member->position, $member->project_code, (float) $member->basic_pay, $member->currency, $member->active ? 1 : 0];
        }
    }

    public function exampleRows(array $context): array
    {
        return [[68, 'Joseph Shenekji', 'Project Manager', (string) GlProject::query()->orderBy('code')->value('code'), 1000, 'USD', 1]];
    }

    public function referenceSheets(array $context): array
    {
        return [
            __('excel.sheet.projects') => [
                'headings' => [__('excel.col.project'), __('excel.col.name')],
                'rows' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get()->map(fn (GlProject $project): array => [$project->code, $project->name])->all(),
            ],
        ];
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $problems = [];
        $existing = ctype_digit($cells['employee_id']) ? StaffMember::query()->where('employee_id', (int) $cells['employee_id'])->first() : null;

        if (! ctype_digit($cells['employee_id']) || (int) $cells['employee_id'] <= 0) {
            $problems[] = __('payroll.problem.employee_id');
        }

        if ($cells['name'] === '') {
            $problems[] = __('payroll.problem.name');
        }

        if (! GlProject::query()->whereKey($cells['project'])->exists()) {
            $problems[] = __('payroll.problem.project');
        }

        if (! is_numeric(str_replace(',', '', $cells['basic_pay'])) || (float) str_replace(',', '', $cells['basic_pay']) <= 0) {
            $problems[] = __('payroll.problem.basic_pay');
        }

        if (! in_array(strtoupper($cells['currency']), Payrolls::CURRENCIES, true)) {
            $problems[] = __('payroll.problem.currency');
        }

        if ($this->active($cells['active']) === null) {
            $problems[] = __('excel.bad_yes_no', ['column' => __('excel.col.active')]);
        }

        if (in_array($cells['employee_id'], array_column($before, 'employee_id'), true)) {
            $problems[] = __('excel.twice');
        }

        if ($existing !== null && ! Gate::forUser($user)->allows('screen', ['payroll', 'edit'])) {
            $problems[] = __('excel.no_update_right');
        }

        return ['action' => $existing !== null ? 'update' : 'new', 'problems' => $problems];
    }

    /** @throws PayrollException */
    protected function saveRow(array $cells, User $user, array $context): void
    {
        $this->payrolls->saveStaff(StaffMember::query()->where('employee_id', (int) $cells['employee_id'])->first(), [
            'employee_id' => $cells['employee_id'], 'name' => $cells['name'], 'position' => $cells['position'], 'project' => $cells['project'],
            'basic_pay' => str_replace(',', '', $cells['basic_pay']), 'currency' => strtoupper($cells['currency']),
            'active' => $this->active($cells['active']) ? '1' : '0',
        ]);
    }

    /** Yes or no; an empty cell is yes. */
    private function active(string $text): ?bool
    {
        $text = mb_strtolower(trim($text));

        return match (true) {
            $text === '' || in_array($text, self::YES, true) => true,
            in_array($text, self::NO, true) => false,
            default => null,
        };
    }
}
