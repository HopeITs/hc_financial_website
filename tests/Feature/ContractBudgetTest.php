<?php

use App\Enums\RoleId;
use App\Livewire\ContractBudget;
use App\Livewire\ContractPage;
use App\Livewire\Contracts;
use App\Models\Contract;
use App\Models\ContractAudit;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\Donor;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Audit\AuditLog as AuditLogQuery;
use App\Services\Contracts\ContractActuals;
use App\Services\Contracts\ContractBudgets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    createFinanceSchema();

    foreach ([['5101', 'expenses', 'Salaries', 'رواتب'], ['5201', 'expenses', 'Rent', 'إيجار'], ['1111', 'assets', 'Cash', 'نقد'], ['4101', 'income', 'Grants', 'منح']] as [$code, $class, $en, $ar]) {
        GlAccount::query()->create([
            'code' => $code, 'parent_code' => null, 'level' => 'posting', 'class' => $class, 'name_en' => $en, 'name_ar' => $ar,
            'normal_side' => 'debit', 'currency' => 'USD', 'is_cash' => $class === 'assets', 'required_tags' => [], 'is_active' => true,
        ]);
    }

    GlProject::query()->create(['code' => 'EDU', 'name' => 'Education', 'restriction' => 'With donor restrictions', 'donor_currency' => 'EUR', 'donor_rate' => 1.15, 'is_general_account' => false]);
    GlProject::query()->create(['code' => 'OUT', 'name' => 'Outside', 'restriction' => 'With donor restrictions', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false]);
});

function cbUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** A budget line of project EDU, added by a set-up user so that the test's own users stay apart. */
function cbLine(Contract $contract, string $name, string $account, string $amount): void
{
    $setup = User::query()->where('Username', 'Setup')->first() ?? cbUser(RoleId::Accountant, 'Setup');

    app(ContractBudgets::class)->add($contract, 'EDU', $name, $account, $amount, $setup);
}

/** An EUR 10,000 draft agreement whose one project EDU has the whole amount as its share. */
function cbAgreement(): Contract
{
    $donor = Donor::query()->create([
        'code' => 'CARPL', 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس بولندا', 'type' => 'church_agency',
        'country' => 'Poland', 'currency' => 'EUR', 'is_active' => true,
    ]);

    $contract = Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => $donor->id, 'title' => 'Education 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28',
        'currency' => 'EUR', 'amount' => '10000', 'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'draft', 'created_by' => 1,
    ]);
    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'EDU', 'amount' => '10000']);

    return $contract;
}

/**
 * A voucher with its lines, written straight to the ledger tables.
 *
 * @param  list<array<string, mixed>>  $lines
 */
function cbVoucher(string $id, string $date, string $status, array $lines): void
{
    DB::table('gl_vouchers')->insert(['id' => $id, 'type' => 'JV', 'date' => $date, 'status' => $status, 'prepared_by' => 1, 'created_at' => now(), 'updated_at' => now()]);

    foreach ($lines as $number => $line) {
        DB::table('gl_voucher_lines')->insert($line + [
            'voucher_id' => $id, 'line_no' => $number + 1, 'currency' => 'EUR', 'rate' => 1,
            'debit_orig' => 0, 'credit_orig' => 0, 'debit_usd' => 0, 'credit_usd' => 0, 'debit_donor' => 0, 'credit_donor' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** A budget workbook with the given rows under the header row, as an uploaded file. */
function cbSheet(array $rows, array $headers = ['Budget Category', 'Budget Line Type', 'Budget Line Name', 'Total/EUR']): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cbud').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($headers));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return UploadedFile::fake()->createWithContent('budget.xlsx', (string) file_get_contents($path));
}

it('reads an Excel budget, proposes an account for each line and adds the checked budget at once', function () {
    $contract = cbAgreement();
    GlBudgetLine::query()->create(['key' => 'OUT|Books', 'project_code' => 'OUT', 'name' => 'Books', 'account_code' => '5201', 'budget' => 1, 'currency' => 'USD']);
    cbLine($contract, 'Old line', '5101', '100');

    $page = Livewire::actingAs(cbUser(RoleId::Accountant, 'Ann'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->set('importProject', 'EDU')
        ->set('budgetFile', cbSheet([
            ['Personnel', 'Salaries', 'Teachers', 6000],
            ['Operations', 'Rent', 'Office rent', 2400],
            ['Activities', '', 'Books', 500],
            ['Activities', 'Something', 'Mystery item', 1000],
            ['Operations', '', 'Old line', 100],
            ['Total', '', 'Total', 10000],
        ]))
        ->call('readBudgetFile')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-test="budget-import-preview"')
        ->assertSeeHtml('data-test="import-unassigned"');

    expect(collect($page->get('importRows'))->map(fn (array $row) => [$row['name'], $row['account'], $row['source'], $row['amount'], $row['include']])->all())->toBe([
        ['Teachers', '5101', 'rule', '6000.00', true],
        ['Office rent', '5201', 'chart', '2400.00', true],
        ['Books', '5201', 'learned', '500.00', true],
        ['Mystery item', '', '', '1000.00', true],
        ['Old line', '5101', 'learned', '100.00', false],
    ]);

    $page->call('importBudget')
        ->assertHasErrors(['importRows.3.account' => 'required']);

    expect(GlBudgetLine::query()->where('project_code', 'EDU')->count())->toBe(1);

    $page->set('importRows.3.account', '5201')
        ->call('importBudget')
        ->assertHasNoErrors()
        ->assertSee('4 budget lines added.')
        ->assertSet('importRows', [])
        ->assertDispatched('budget-changed');

    expect(GlBudgetLine::query()->where('project_code', 'EDU')->orderBy('name')->pluck('account_code', 'name')->all())->toBe([
        'Books' => '5201', 'Mystery item' => '5201', 'Office rent' => '5201', 'Old line' => '5101', 'Teachers' => '5101',
    ])->and(GlBudgetLine::query()->findOrFail('EDU|Teachers')->currency)->toBe('EUR');

    $import = ContractAudit::query()->where('action', 'budget_imported')->sole();

    expect($import->details)->toBe(['lines' => 4, 'total' => '9900.00', 'currency' => 'EUR'])
        ->and($import->after_approval)->toBeFalse()
        ->and(ContractAudit::query()->where('action', 'budget_line_added')->get()->filter(fn (ContractAudit $entry) => ($entry->details['source'] ?? null) === 'excel')->count())->toBe(4);
});

it('refuses a file without a budget table, and adds nothing when a ticked name is already taken', function () {
    $contract = cbAgreement();

    Livewire::actingAs(cbUser(RoleId::Accountant, 'Ann'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->set('importProject', 'EDU')
        ->set('budgetFile', cbSheet([['a', 'b']], ['Foo', 'Bar']))
        ->call('readBudgetFile')
        ->assertHasErrors(['budgetFile'])
        ->assertSet('importRows', [])
        ->set('budgetFile', cbSheet([['Personnel', 'Salaries', 'Teachers', 6000], ['Personnel', 'Salaries', 'Nurses', 4000]]))
        ->call('readBudgetFile')
        ->set('importRows.1.name', 'Teachers')
        ->call('importBudget')
        ->assertSee('These names are already in the project\'s budget or appear twice: teachers.');

    expect(GlBudgetLine::query()->count())->toBe(0);
});

it('shows what was spent on each budget line and received for each project, from approved vouchers only', function () {
    $contract = cbAgreement();
    cbLine($contract, 'Teachers', '5101', '6000');
    cbLine($contract, 'Rent', '5201', '4000');

    cbVoucher('JV-2026-0001', '2026-04-01', 'approved', [
        ['account_code' => '5101', 'project_code' => 'EDU', 'budget_line_key' => 'EDU|Teachers', 'debit_orig' => 1000, 'debit_usd' => 1150],
        ['account_code' => '5101', 'project_code' => 'EDU', 'budget_line_key' => 'EDU|Teachers', 'currency' => 'USD', 'donor_currency' => 'EUR', 'debit_orig' => 230, 'debit_usd' => 230, 'debit_donor' => 200],
        ['account_code' => '1111', 'credit_orig' => 1200, 'credit_usd' => 1380],
    ]);
    cbVoucher('JV-2026-0002', '2026-04-02', 'pending_approval', [
        ['account_code' => '5201', 'project_code' => 'EDU', 'budget_line_key' => 'EDU|Rent', 'debit_orig' => 500, 'debit_usd' => 575],
        ['account_code' => '1111', 'credit_orig' => 500, 'credit_usd' => 575],
    ]);
    cbVoucher('CRV-2026-0001', '2026-03-15', 'approved', [
        ['account_code' => '1111', 'debit_orig' => 3000, 'debit_usd' => 3450],
        ['account_code' => '4101', 'project_code' => 'EDU', 'credit_orig' => 3000, 'credit_usd' => 3450],
    ]);
    cbVoucher('CRV-2026-0002', '2026-01-10', 'approved', [
        ['account_code' => '1111', 'debit_orig' => 999, 'debit_usd' => 1148.85],
        ['account_code' => '4101', 'project_code' => 'EDU', 'credit_orig' => 999, 'credit_usd' => 1148.85],
    ]);

    $actuals = app(ContractActuals::class);

    expect($actuals->spentByLine($contract, ['EDU|Teachers', 'EDU|Rent']))->toBe(['EDU|Teachers' => '1200.00'])
        ->and($actuals->receivedByProject($contract))->toBe(['EDU' => '3000.00']);

    Livewire::actingAs(cbUser(RoleId::Accountant, 'Ann'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->assertSeeHtml('data-test="spent-EDU|Teachers"')
        ->assertSeeHtml('data-test="received-EDU"')
        ->assertViewHas('sections', fn ($sections) => $sections->first()['spent'] === '1200.00'
            && $sections->first()['remaining'] === '8800.00'
            && $sections->first()['received'] === '3000.00');
});

it('adds budget lines to a project of the agreement, in its currency, and compares them with the share', function () {
    $contract = cbAgreement();

    $page = Livewire::actingAs(cbUser(RoleId::Accountant, 'Ann'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->set('lineProject', 'EDU')->set('lineName', ' Teachers ')->set('lineAccount', '5101')->set('lineAmount', '6000')
        ->call('addLine')
        ->assertHasNoErrors()
        ->assertSee('Budget line added.')
        ->assertSee('Budget does not equal the share yet')
        ->set('lineProject', 'EDU')->set('lineName', 'Rent')->set('lineAccount', '5201')->set('lineAmount', '4000')
        ->call('addLine')
        ->assertSee('Budget equals the share');

    $line = GlBudgetLine::query()->findOrFail('EDU|Teachers');

    expect($line->name)->toBe('Teachers')
        ->and($line->account_code)->toBe('5101')
        ->and($line->currency)->toBe('EUR')
        ->and($line->donor)->toBe('Caritas Poland')
        ->and($line->budget)->toBe('6000.00');

    $page->set('lineProject', 'EDU')->set('lineName', 'Teachers')->set('lineAccount', '5101')->set('lineAmount', '1')
        ->call('addLine')->assertHasErrors(['lineName'])
        ->set('lineProject', 'OUT')->set('lineName', 'Other')->set('lineAccount', '1111')->set('lineAmount', '1')
        ->call('addLine')->assertHasErrors(['lineProject' => 'in', 'lineAccount' => 'in']);

    expect(GlBudgetLine::query()->count())->toBe(2);
});

it('removes an unused budget line but keeps one that is already used', function () {
    $contract = cbAgreement();
    $accountant = cbUser(RoleId::Accountant, 'Ann');
    cbLine($contract, 'Teachers', '5101', '100');
    cbLine($contract, 'Rent', '5201', '50');

    Livewire::actingAs($accountant)->test(ContractBudget::class, ['contractId' => $contract->id])
        ->call('removeLine', 'EDU|Rent')
        ->assertSee('Budget line removed.');

    expect(GlBudgetLine::query()->find('EDU|Rent'))->toBeNull()
        ->and((int) ContractAudit::query()->where('action', 'budget_line_removed')->where('budget_line_key', 'EDU|Rent')->value('changed_by'))->toBe((int) $accountant->getKey());

    $this->mock(ContractBudgets::class, fn ($mock) => $mock->shouldReceive('isUsed')->andReturnTrue());

    Livewire::actingAs($accountant)->test(ContractBudget::class, ['contractId' => $contract->id])
        ->call('removeLine', 'EDU|Teachers')
        ->assertSee('This budget line is already used by an entry, a request or a salary split, so it cannot be removed.');

    expect(GlBudgetLine::query()->find('EDU|Teachers'))->not->toBeNull();
});

it('lets only the preparing roles change the budget', function () {
    $contract = cbAgreement();

    Livewire::actingAs(cbUser(RoleId::GeneralManager, 'Gina'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->assertDontSeeHtml('data-test="budget-form"')
        ->set('lineProject', 'EDU')->set('lineName', 'X')->set('lineAccount', '5101')->set('lineAmount', '1')
        ->call('addLine')
        ->assertForbidden();

    expect(GlBudgetLine::query()->count())->toBe(0);
});

it('warns before approving an unmatched budget, approves anyway, and marks the agreement until the budget is matched', function () {
    $contract = cbAgreement();
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 1, 'due_on' => '2026-03-01', 'amount' => '10000', 'is_conditional' => false]);
    cbLine($contract, 'Teachers', '5101', '6000');
    $manager = cbUser(RoleId::FinancialManager, 'Fadi');

    expect(app(ContractBudgets::class)->mismatches($contract))->toBe([['project' => 'EDU', 'total' => '6000.00', 'share' => '10000.00']]);

    $page = Livewire::actingAs($manager)->test(ContractPage::class, ['contract' => $contract])
        ->assertSeeHtml('data-test="budget-warning"')
        ->assertSee('The budget of EDU adds up to 6000.00, not to its share 10000.00.')
        ->assertSee(__('contracts.approve_confirm_budget'))
        ->call('approve')
        ->assertSee('Agreement approved.')
        ->assertSeeHtml('data-test="needs-matching"');

    expect($contract->refresh()->isApproved())->toBeTrue()
        ->and(app(ContractBudgets::class)->mismatchedIds(collect([$contract])))->toBe([$contract->id]);

    Livewire::actingAs($manager)->test(Contracts::class)->assertSeeHtml('data-test="needs-matching"');

    Livewire::actingAs($manager)->test(ContractBudget::class, ['contractId' => $contract->id])
        ->assertSeeHtml('data-test="budget-needs-matching"')
        ->set('lineProject', 'EDU')->set('lineName', 'Rent')->set('lineAccount', '5201')->set('lineAmount', '4000')
        ->call('addLine')
        ->assertHasNoErrors()
        ->assertDispatched('budget-changed')
        ->assertDontSeeHtml('data-test="budget-needs-matching"');

    $page->call('refreshBudget')->assertDontSeeHtml('data-test="needs-matching"')->assertDontSeeHtml('data-test="budget-warning"');

    expect(app(ContractBudgets::class)->mismatchedIds(collect([$contract->refresh()])))->toBe([]);

    // The budget changes are in the agreement's trail, the one after approval marked so, and in the audit log screen.
    expect(ContractAudit::query()->where('budget_line_key', 'EDU|Teachers')->value('after_approval'))->toBeFalsy()
        ->and(ContractAudit::query()->where('budget_line_key', 'EDU|Rent')->sole()->after_approval)->toBeTrue()
        ->and(app(AuditLogQuery::class)->query(['source' => 'agreement'])->pluck('reason')->all())->toEqualCanonicalizing(['EDU|Rent', 'EDU|Teachers']);

    Livewire::actingAs($manager)->test(ContractBudget::class, ['contractId' => $contract->id])
        ->assertSeeHtml('data-test="budget-history"')
        ->assertSee('After approval')
        ->assertSee('Fadi');
});
