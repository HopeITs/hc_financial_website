<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\Salaries;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\MonthClose;
use App\Models\PayrollSplit;
use App\Models\User;
use App\Models\VoucherLine;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\PayrollSplits;
use App\Services\Payroll\SalaryAllocation;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    Schema::connection('legacy')->create('role_currency_limit', function ($table) {
        $table->integer('Role_ID');
        $table->integer('TillType_ID');
        $table->decimal('Approval_Limit', 18, 2);
    });
    DB::connection('legacy')->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);
    DB::connection('legacy')->table('role_currency_limit')->insert(['Role_ID' => RoleId::FinancialManager->value, 'TillType_ID' => 2, 'Approval_Limit' => 5000]);

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (require database_path('migrations/2026_10_26_090000_create_gl_payroll_splits_table.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    foreach (['F2F', 'SZ', 'CORE'] as $code) {
        GlBudgetLine::query()->create(['key' => $code.'|Staff', 'project_code' => $code, 'name' => 'Staff', 'account_code' => '5101', 'budget' => 100000, 'currency' => 'USD']);
    }
});

function payrollSplit(int $employee = 7, string $from = '2026-03-01', array $shares = ['F2F|Staff' => '50', 'SZ|Staff' => '30', 'CORE|Staff' => '20']): void
{
    foreach ($shares as $key => $percent) {
        PayrollSplit::query()->create([
            'legacy_employee_id' => $employee, 'project_code' => explode('|', $key)[0], 'budget_line_key' => $key, 'percent' => $percent, 'valid_from' => $from,
        ]);
    }
}

function payrollUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function allocate(string $amount, array $lines, string $currency = 'USD', string $rate = '', string $cash = '1111', int $by = 5)
{
    return app(SalaryAllocation::class)->post(
        CarbonImmutable::parse('2026-03-31'), 'March salary', $currency, $rate, $amount, $lines, $cash, FundSource::DonorGrant, $by,
    );
}

it('refuses a split whose percentages do not add up to 100', function () {
    $splits = app(PayrollSplits::class);

    expect(fn () => $splits->replace(7, 'Sara', CarbonImmutable::parse('2026-03-01'), [
        ['budget_line' => 'F2F|Staff', 'percent' => '60'], ['budget_line' => 'SZ|Staff', 'percent' => '30'],
    ]))->toThrow(PayrollException::class);
    expect(PayrollSplit::query()->count())->toBe(0);

    $splits->replace(7, 'Sara', CarbonImmutable::parse('2026-03-01'), [
        ['budget_line' => 'F2F|Staff', 'percent' => '60.50'], ['budget_line' => 'SZ|Staff', 'percent' => '39.50'],
    ]);
    expect(PayrollSplit::query()->where('legacy_employee_id', 7)->count())->toBe(2);
});

it('refuses a repeated budget line and a percent outside 0 to 100', function () {
    $splits = app(PayrollSplits::class);
    $day = CarbonImmutable::parse('2026-03-01');

    expect(fn () => $splits->replace(7, null, $day, [['budget_line' => 'F2F|Staff', 'percent' => '50'], ['budget_line' => 'F2F|Staff', 'percent' => '50']]))
        ->toThrow(PayrollException::class)
        ->and(fn () => $splits->replace(7, null, $day, [['budget_line' => 'F2F|Staff', 'percent' => '0'], ['budget_line' => 'SZ|Staff', 'percent' => '100']]))
        ->toThrow(PayrollException::class);
});

it('starts a change made during a month on the first of the next month', function () {
    $splits = app(PayrollSplits::class);
    payrollSplit(from: '2026-03-01');

    $from = $splits->replace(7, 'Sara', CarbonImmutable::parse('2026-04-15'), [['budget_line' => 'F2F|Staff', 'percent' => '100']]);

    expect($from->toDateString())->toBe('2026-05-01')
        ->and($splits->forMonth(7, CarbonImmutable::parse('2026-04-01'))->pluck('budget_line_key')->all())->toBe(['F2F|Staff', 'SZ|Staff', 'CORE|Staff'])
        ->and($splits->forMonth(7, CarbonImmutable::parse('2026-05-01'))->pluck('budget_line_key')->all())->toBe(['F2F|Staff'])
        ->and($splits->forMonth(7, CarbonImmutable::parse('2026-02-01')))->toBeEmpty()
        ->and($splits->effectiveFrom(CarbonImmutable::parse('2026-06-01'))->toDateString())->toBe('2026-06-01');
});

it('proposes the lines from the split and gives the cents to the last line', function () {
    payrollSplit(shares: ['F2F|Staff' => '33.33', 'SZ|Staff' => '33.33', 'CORE|Staff' => '33.34']);

    $lines = app(SalaryAllocation::class)->propose(7, CarbonImmutable::parse('2026-03-01'), '100.01');

    expect(array_column($lines, 'amount'))->toBe(['33.33', '33.33', '33.35'])
        ->and(array_sum(array_map('floatval', array_column($lines, 'amount'))))->toEqualWithDelta(100.01, 0.0001);
});

it('refuses to propose lines for an employee without a split', function () {
    expect(fn () => app(SalaryAllocation::class)->propose(99, CarbonImmutable::parse('2026-03-01'), '500'))
        ->toThrow(PayrollException::class, '99');
});

it('posts an ALC voucher with one debit per budget line and one cash credit per project', function () {
    payrollSplit();
    $lines = app(SalaryAllocation::class)->propose(7, CarbonImmutable::parse('2026-03-01'), '1000');

    $voucher = allocate('1000', $lines);

    $debits = $voucher->lines->where('debit_usd', '>', 0);
    $credits = $voucher->lines->where('credit_usd', '>', 0);

    expect($voucher->type)->toBe(VoucherType::Allocation)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->id)->toStartWith('ALC-2026-')
        ->and($debits)->toHaveCount(3)
        ->and($debits->pluck('account_code')->unique()->all())->toBe(['5101'])
        ->and($debits->pluck('debit_usd')->all())->toBe(['500.00', '300.00', '200.00'])
        ->and($credits)->toHaveCount(3)
        ->and($credits->pluck('account_code')->unique()->all())->toBe(['1111'])
        ->and($credits->pluck('project_code')->all())->toBe(['F2F', 'SZ', 'CORE'])
        ->and($credits->sum(fn (VoucherLine $line) => (float) $line->credit_usd))->toBe(1000.0);
});

it('keeps the voucher balanced to the cent when the shares do not divide evenly', function () {
    $voucher = allocate('100.01', [
        ['budget_line' => 'F2F|Staff', 'amount' => '33.33'], ['budget_line' => 'SZ|Staff', 'amount' => '33.33'], ['budget_line' => 'CORE|Staff', 'amount' => '33.35'],
    ]);

    expect($voucher->lines->sum(fn (VoucherLine $line) => (float) $line->debit_usd))->toEqualWithDelta(100.01, 0.0001)
        ->and($voucher->lines->sum(fn (VoucherLine $line) => (float) $line->credit_usd))->toEqualWithDelta(100.01, 0.0001)
        ->and($voucher->lines)->toHaveCount(6);
});

it('converts a pound salary at the typed rate and balances in USD', function () {
    $voucher = allocate('3000000', [
        ['budget_line' => 'F2F|Staff', 'amount' => '1000000'], ['budget_line' => 'SZ|Staff', 'amount' => '1000000'], ['budget_line' => 'CORE|Staff', 'amount' => '1000000'],
    ], 'SYP', '15000', '1112');

    $debits = $voucher->lines->where('debit_orig', '>', 0)->values();

    expect($debits->pluck('currency')->unique()->all())->toBe(['SYP'])
        ->and($debits->pluck('debit_orig')->all())->toBe(['1000000.00', '1000000.00', '1000000.00'])
        ->and($debits->sum(fn (VoucherLine $line) => (float) $line->debit_usd))->toEqualWithDelta(200.0, 0.0001)
        ->and($voucher->lines->where('account_code', '1112')->sum(fn (VoucherLine $line) => (float) $line->credit_usd))->toEqualWithDelta(200.0, 0.0001);
});

it('refuses a pound salary without a rate and a cash account in the wrong currency', function () {
    $lines = [['budget_line' => 'F2F|Staff', 'amount' => '1000']];

    expect(fn () => allocate('1000', $lines, 'SYP', '', '1112'))->toThrow(PayrollException::class)
        ->and(fn () => allocate('1000', $lines, 'SYP', '15000', '1111'))->toThrow(PayrollException::class)
        ->and(fn () => allocate('1000', $lines, 'USD', '', '4101'))->toThrow(PayrollException::class);
});

it('refuses lines that do not add up to the salary', function () {
    expect(fn () => allocate('1000', [['budget_line' => 'F2F|Staff', 'amount' => '999.99']]))->toThrow(PayrollException::class);
    expect(DB::table('gl_vouchers')->count())->toBe(0);
});

it('shares an invoice between projects with lines typed by hand', function () {
    $voucher = allocate('250', [['budget_line' => 'F2F|Staff', 'amount' => '100'], ['budget_line' => 'SZ|Staff', 'amount' => '150']]);

    expect($voucher->type)->toBe(VoucherType::Allocation)
        ->and($voucher->lines->where('debit_usd', '>', 0)->pluck('project_code')->all())->toBe(['F2F', 'SZ']);
});

it('warns about a project that has ended but still lets the voucher be saved', function () {
    GlProject::query()->whereKey('SZ')->update(['end_date' => '2026-02-28']);
    $lines = [['budget_line' => 'F2F|Staff', 'amount' => '100'], ['budget_line' => 'SZ|Staff', 'amount' => '150']];

    $warnings = app(SalaryAllocation::class)->warnings($lines, CarbonImmutable::parse('2026-03-31'));

    expect($warnings)->toHaveCount(1)->and($warnings[0])->toContain('SZ');
    expect(allocate('250', $lines)->id)->toStartWith('ALC-');
});

it('refuses to save into a closed month', function () {
    MonthClose::query()->create(['month' => '2026-03', 'closed_by' => 9, 'closed_at' => now()]);

    expect(fn () => allocate('100', [['budget_line' => 'F2F|Staff', 'amount' => '100']]))->toThrow(LedgerRuleException::class);
});

it('does not let the preparer approve their own allocation', function () {
    $accountant = payrollUser(RoleId::FinancialManager, 'Fatima');
    $voucher = allocate('100', [['budget_line' => 'F2F|Staff', 'amount' => '100']], by: (int) $accountant->getAuthIdentifier());

    expect(fn () => app(VoucherApprovals::class)->approve($voucher, $accountant))->toThrow(LedgerRuleException::class);

    $other = payrollUser(RoleId::FinancialManager, 'Omar');
    expect(app(VoucherApprovals::class)->approve($voucher, $other)->status)->toBe(VoucherStatus::Approved);
});

it('lets the accountant open the salaries screen, propose the lines and save the voucher', function () {
    payrollSplit();
    $ann = payrollUser(RoleId::Accountant, 'Ann');

    Livewire::actingAs($ann)->test(Salaries::class)
        ->set('employeeId', '7')->set('month', '2026-03')->set('date', '2026-03-31')->set('amount', '1000')
        ->set('cashAccount', '1111')
        ->call('propose')
        ->assertSet('lines.0.amount', '500.00')
        ->assertSet('lines.2.amount', '200.00')
        ->call('post')
        ->assertSet('failures', [])
        ->assertSee('ALC-2026-');

    expect(DB::table('gl_vouchers')->where('type', 'ALC')->count())->toBe(1);
});

it('shows a clear message when the employee has no split', function () {
    Livewire::actingAs(payrollUser(RoleId::Accountant, 'Ann'))->test(Salaries::class)
        ->set('employeeId', '42')->set('month', '2026-03')->set('amount', '1000')
        ->call('propose')
        ->assertSet('failures', [__('salaries.problem.no_split', ['employee' => 42, 'month' => '2026-03'])]);
});

it('saves a payroll split from the screen', function () {
    Livewire::actingAs(payrollUser(RoleId::Accountant, 'Ann'))->test(Salaries::class)
        ->set('tab', 'splits')->set('employeeId', '7')->set('employeeName', 'Sara')->set('changeDate', '2026-03-01')
        ->set('splitRows', [['budget_line' => 'F2F|Staff', 'percent' => '70'], ['budget_line' => 'SZ|Staff', 'percent' => '30']])
        ->call('saveSplit')
        ->assertSet('failures', []);

    expect(PayrollSplit::query()->where('legacy_employee_id', 7)->pluck('percent')->all())->toBe(['70.00', '30.00']);
});

it('keeps salaries away from the roles that are not finance or head office', function () {
    foreach ([RoleId::ProjectManager, RoleId::Cashier, RoleId::Employee, RoleId::Donor] as $role) {
        $this->actingAs(payrollUser($role, 'User '.$role->name))->get(route('salaries'))->assertForbidden();
    }

    $this->actingAs(payrollUser(RoleId::Accountant, 'Ann'))->get(route('salaries'))->assertOk();
});

it('lets the financial manager view the screen but not save', function () {
    Livewire::actingAs(payrollUser(RoleId::FinancialManager, 'Fatima'))->test(Salaries::class)
        ->set('employeeId', '7')->set('amount', '100')->set('cashAccount', '1111')
        ->call('post')
        ->assertForbidden();
});
