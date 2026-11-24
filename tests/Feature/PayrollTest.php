<?php

use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\PayrollScreen;
use App\Models\GlBudgetLine;
use App\Models\Payroll;
use App\Models\PayrollSplit;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\Excel\Datasets\PayrollLinesExcel;
use App\Services\Excel\Datasets\StaffExcel;
use App\Services\Excel\ExcelException;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\Payrolls;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    DB::connection('legacy')->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (require database_path('migrations/2026_10_26_090000_create_gl_payroll_splits_table.php'))->up();
    (require database_path('migrations/2026_11_25_100000_create_payroll_tables.php'))->up();
    (require database_path('migrations/2026_11_25_110000_create_project_revenues.php'))->up();
    seedChartWithTestProjects();

    foreach (['F2F', 'SZ'] as $code) {
        GlBudgetLine::query()->create(['key' => $code.'|Staff', 'project_code' => $code, 'name' => 'Staff', 'account_code' => '5101', 'budget' => 100000, 'currency' => 'USD']);
    }
});

function prUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** @param array<string, string> $shares budget line => percent */
function prSplit(int $employee, array $shares): void
{
    foreach ($shares as $key => $percent) {
        PayrollSplit::query()->create(['legacy_employee_id' => $employee, 'project_code' => explode('|', $key)[0], 'budget_line_key' => $key, 'percent' => $percent, 'valid_from' => '2026-01-01']);
    }
}

/** Joseph (#68, 1,000 USD, half on F2F and half on SZ) and Rana (#69, 600 USD, all on F2F), on F2F's payroll. */
function prStaff(): void
{
    $payrolls = app(Payrolls::class);
    $payrolls->saveStaff(null, ['employee_id' => '68', 'name' => 'Joseph', 'position' => 'Project Manager', 'project' => 'F2F', 'basic_pay' => '1000', 'currency' => 'USD']);
    $payrolls->saveStaff(null, ['employee_id' => '69', 'name' => 'Rana', 'position' => 'Teacher', 'project' => 'F2F', 'basic_pay' => '600', 'currency' => 'USD']);
    prSplit(68, ['F2F|Staff' => '50', 'SZ|Staff' => '50']);
    prSplit(69, ['F2F|Staff' => '100']);
}

it('makes the month\'s payroll: unpaid days, bonus, the Financial Manager\'s rate and approval, and one voucher on the budget lines', function () {
    prStaff();
    $ann = prUser(RoleId::Accountant, 'Ann');
    $fatima = prUser(RoleId::FinancialManager, 'Fatima');
    $payrolls = app(Payrolls::class);

    $payroll = $payrolls->open($ann, 'F2F', '2026-10', 'SYP');
    expect($payroll->lines()->count())->toBe(2);

    $payroll = $payrolls->setRate($payroll, '13000');
    $joseph = $payroll->lines()->where('employee_id', 68)->sole();
    $rana = $payroll->lines()->where('employee_id', 69)->sole();

    // 4.2 unpaid days of 1,000 USD is 140 USD, as on the payslip template.
    $payrolls->updateLine($payroll, $joseph, ['adjustment' => '0', 'unpaid_days' => '4.2', 'other_deductions' => '0', 'notes' => '']);
    $payrolls->updateLine($payroll, $rana, ['adjustment' => '50', 'unpaid_days' => '0', 'other_deductions' => '10', 'notes' => 'Bonus']);

    expect([$joseph->refresh()->unpaid_leave, $joseph->net, $joseph->net_paid])->toBe(['140.00', '860.00', '11180000.00'])
        ->and(array_column($joseph->allocations, 'amount'))->toBe(['5590000.00', '5590000.00'])
        ->and([$rana->refresh()->net, $rana->net_paid])->toBe(['640.00', '8320000.00'])
        ->and($payrolls->totals($payroll->refresh()->load('lines'))['paid'])->toBe('19500000.00');

    $payrolls->submit($payroll);
    expect($payrolls->mayApprove($ann, $payroll->refresh()))->toBeFalse();

    $payrolls->approve($fatima, $payroll);
    $voucher = $payrolls->pay($ann, $payroll->refresh(), '1112', '2026-10-30');

    $debits = VoucherLine::query()->where('voucher_id', $voucher->id)->where('debit_orig', '>', 0)->orderBy('budget_line_key')->get()
        ->map(fn (VoucherLine $line) => [$line->budget_line_key, $line->debit_orig])->all();

    expect($voucher->type)->toBe(VoucherType::Allocation)
        ->and($debits)->toBe([['F2F|Staff', '13910000.00'], ['SZ|Staff', '5590000.00']])
        ->and($payroll->refresh()->status)->toBe(Payroll::PAID);

    // A rejected payroll voucher puts the payroll back to approved.
    app(VoucherApprovals::class)->reject(Voucher::query()->findOrFail($voucher->id), $fatima, 'Wrong account');
    expect($payroll->refresh()->status)->toBe(Payroll::APPROVED)
        ->and($payroll->voucher_id)->toBeNull();
});

it('refuses a second payroll for the month, a missing split and a missing rate', function () {
    prStaff();
    $ann = prUser(RoleId::Accountant, 'Ann');
    $payrolls = app(Payrolls::class);
    $payrolls->saveStaff(null, ['employee_id' => '70', 'name' => 'Sami', 'project' => 'F2F', 'basic_pay' => '500', 'currency' => 'USD']);

    $payroll = $payrolls->open($ann, 'F2F', '2026-10', 'USD');

    expect(fn () => $payrolls->open($ann, 'F2F', '2026-10', 'USD'))->toThrow(PayrollException::class)
        ->and(fn () => $payrolls->submit($payroll))->toThrow(PayrollException::class, 'Sami');

    $payrolls->removeLine($payroll, $payroll->lines()->where('employee_id', 70)->sole());
    expect($payrolls->submit($payroll)->status)->toBe(Payroll::SUBMITTED);
});

it('shows the payroll and prints the summary and the payslips', function () {
    prStaff();
    $ann = prUser(RoleId::Accountant, 'Ann');
    $payroll = app(Payrolls::class)->open($ann, 'F2F', '2026-10', 'USD');

    Livewire::actingAs($ann)->withQueryParams(['payroll' => (string) $payroll->id])->test(PayrollScreen::class)
        ->assertSee('Joseph')->assertSee('Rana')
        ->assertSeeHtml('data-test="submit-payroll"');

    $this->actingAs($ann)->get(route('operations.payroll.print', ['payroll' => $payroll->id, 'form' => 'payslips']))
        ->assertOk()->assertSee('Payslip')->assertSee('Joseph')->assertSeeHtml('data-test="payslip-69"');
    $this->actingAs($ann)->get(route('operations.payroll.print', ['payroll' => $payroll->id, 'form' => 'summary']))
        ->assertOk()->assertSee('Payroll Summary');

    $this->actingAs(prUser(RoleId::Cashier, 'Carl'))->get(route('operations.payroll'))->assertForbidden();
});

it('adds staff from Excel and takes a month\'s absences, bonuses and deductions from Excel', function () {
    $ann = prUser(RoleId::Accountant, 'Ann');
    $staff = app(StaffExcel::class);

    $read = [
        ['row' => 2, 'cells' => ['68', 'Joseph', 'Project Manager', 'F2F', '1000', 'usd', '1']],
        ['row' => 3, 'cells' => ['69', 'Rana', 'Teacher', 'F2F', '600', 'USD', '']],
    ];
    expect($staff->save($read, $ann, []))->toBe(2)
        ->and(StaffMember::query()->where('employee_id', 68)->value('currency'))->toBe('USD');

    // A wrong row stops the whole file.
    expect(fn () => $staff->save([['row' => 2, 'cells' => ['70', '', '', 'NOPE', '0', 'EUR', 'maybe']]], $ann, []))->toThrow(ExcelException::class);

    prSplit(68, ['F2F|Staff' => '100']);
    prSplit(69, ['F2F|Staff' => '100']);
    $payroll = app(Payrolls::class)->open($ann, 'F2F', '2026-10', 'USD');

    app(PayrollLinesExcel::class)->save([
        ['row' => 2, 'cells' => ['68', 'Joseph', '0', '4.2', '0', '']],
        ['row' => 3, 'cells' => ['69', 'Rana', '50', '', '10', 'Bonus']],
    ], $ann, ['payroll' => $payroll->id]);

    expect($payroll->lines()->where('employee_id', 68)->value('net'))->toBe('860.00')
        ->and($payroll->lines()->where('employee_id', 69)->value('net'))->toBe('640.00');

    expect(fn () => app(PayrollLinesExcel::class)->save([['row' => 2, 'cells' => ['99', 'Nobody', '0', '0', '0', '']]], $ann, ['payroll' => $payroll->id]))
        ->toThrow(ExcelException::class);
});
