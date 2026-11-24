<?php

use App\Enums\RoleId;
use App\Livewire\ContractPage;
use App\Livewire\DailyCashCount;
use App\Livewire\ExcelTools;
use App\Models\CashCount;
use App\Models\Contract;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\Donor;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Treasury\CashCounts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    (require database_path('migrations/2026_11_25_090000_create_gl_recurring_payments_table.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-20 10:00:00');
});

function dsUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** A draft euro agreement with two instalments. */
function dsAgreement(): Contract
{
    $donor = Donor::query()->create(['code' => 'CARPL', 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس', 'type' => 'church_agency', 'is_active' => true]);
    $contract = Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => $donor->id, 'title' => 'Education 2026', 'signed_on' => '2026-03-01', 'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28',
        'currency' => 'EUR', 'amount' => '10000', 'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'draft', 'created_by' => 1,
    ]);
    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'F2F', 'amount' => '10000']);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 1, 'due_on' => '2026-03-01', 'amount' => '6000', 'is_conditional' => false]);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 2, 'due_on' => '2026-09-01', 'amount' => '4000', 'is_conditional' => false]);

    return $contract;
}

it('changes an instalment from the agreement page while the agreement is not approved', function () {
    $contract = dsAgreement();
    $ann = dsUser(RoleId::Accountant, 'Ann');
    $second = $contract->instalments()->where('number', 2)->firstOrFail();

    Livewire::actingAs($ann)->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->call('editInstalment', $second->id)
        ->assertSet('instalmentAmount', '4000.00')
        ->assertSeeHtml('data-test="save-instalment"')
        ->set('instalmentAmount', '3500')->set('instalmentDue', '2026-10-01')
        ->call('addInstalment')
        ->assertSee(__('contracts.instalment_saved'));

    expect($second->refresh())->toMatchArray(['amount' => '3500.00', 'number' => 2])
        ->and($second->due_on->toDateString())->toBe('2026-10-01')
        ->and($contract->instalments()->count())->toBe(2);

    $contract->update(['approved_by' => 1, 'approved_at' => now(), 'status' => 'running']);

    Livewire::actingAs($ann)->test(ContractPage::class, ['contract' => $contract])
        ->call('editInstalment', $second->id)
        ->assertSee(__('contracts.problem_locked'))
        ->assertSet('editingInstalment', null);
});

it('imports an agreement\'s instalments: a known number changes it, a new one adds it; nothing once approved', function () {
    $contract = dsAgreement();
    $ann = dsUser(RoleId::Accountant, 'Ann');
    $rows = [
        ['row' => 2, 'cells' => ['2', '2026-09-15', '3000', 'no', '']],
        ['row' => 3, 'cells' => ['', '2026-12-01', '1000', 'yes', 'After the final report']],
    ];

    Livewire::actingAs($ann)->test(ExcelTools::class, ['kind' => 'instalments', 'context' => ['contract' => $contract->id]])
        ->set('rows', [...$rows, ['row' => 4, 'cells' => ['x', 'soon', '-5', 'maybe', '']]])
        ->assertSee(__('excel.bad_number'))
        ->call('save')->assertNotDispatched('excel-imported');

    Livewire::actingAs($ann)->test(ExcelTools::class, ['kind' => 'instalments', 'context' => ['contract' => $contract->id]])
        ->set('rows', $rows)->call('save')->assertDispatched('excel-imported')
        ->call('export')->assertFileDownloaded('instalments-2026-11-20.xlsx');

    expect($contract->instalments()->get()->map(fn (ContractInstalment $instalment): array => [$instalment->number, $instalment->due_on->toDateString(), (string) $instalment->amount, $instalment->is_conditional])->all())->toBe([
        [1, '2026-03-01', '6000.00', false],
        [2, '2026-09-15', '3000.00', false],
        [3, '2026-12-01', '1000.00', true],
    ]);

    $contract->update(['approved_by' => 1, 'approved_at' => now(), 'status' => 'running']);

    Livewire::actingAs($ann)->test(ExcelTools::class, ['kind' => 'instalments', 'context' => ['contract' => $contract->id]])
        ->set('rows', $rows)->assertSee(__('contracts.problem_locked'))->call('save')->assertNotDispatched('excel-imported');
});

it('exports the agreements and the open advances, with no import for them', function () {
    dsAgreement();
    $fadi = dsUser(RoleId::FinancialManager, 'Fadi');

    Livewire::actingAs($fadi)->test(ExcelTools::class, ['kind' => 'agreements'])
        ->assertSeeHtml('data-test="excel-export-agreements"')
        ->assertDontSeeHtml('data-test="excel-import-agreements"')
        ->call('export')->assertFileDownloaded('agreements-2026-11-20.xlsx')
        ->call('openImport')->assertForbidden();

    Livewire::actingAs(dsUser(RoleId::Accountant, 'Ann'))->test(ExcelTools::class, ['kind' => 'advances'])
        ->assertDontSeeHtml('data-test="excel-import-advances"')
        ->call('export')->assertFileDownloaded('open-advances-2026-11-20.xlsx');
});

it('deletes a count until its adjustment voucher is made', function () {
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-11-10')]);
    $ann = dsUser(RoleId::Accountant, 'Ann');
    $counts = app(CashCounts::class);
    $today = CarbonImmutable::today();
    $counts->save($today, '1111', '95', 'Short', $ann);

    Livewire::actingAs($ann)->test(DailyCashCount::class)
        ->call('removeCount', '1111')
        ->assertSee(__('treasury.count_removed', ['account' => '1111']));

    expect(CashCount::query()->count())->toBe(0);

    $counts->save($today, '1111', '95', 'Short', $ann);
    $counts->adjust($today, '1111', $ann);

    Livewire::actingAs($ann)->test(DailyCashCount::class)->call('removeCount', '1111')->assertSeeHtml('data-test="problems"');

    expect(CashCount::query()->count())->toBe(1);
});

it('imports a day\'s count from Excel, skipping the accounts left empty', function () {
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-11-10')]);
    $ann = dsUser(RoleId::Accountant, 'Ann');
    $context = ['day' => '2026-11-20', 'city' => 'Aleppo'];

    Livewire::actingAs($ann)->test(ExcelTools::class, ['kind' => 'cash_counts', 'context' => $context])
        ->call('template')->assertFileDownloaded('cash-count-template.xlsx')
        ->set('rows', [
            ['row' => 2, 'cells' => ['1111', '100', '']],
            ['row' => 3, 'cells' => ['1112', '', '']],
            ['row' => 4, 'cells' => ['9999', '5', '']],
        ])
        ->assertSee(__('excel.bad_cash_account', ['account' => '9999']))
        ->call('save')->assertNotDispatched('excel-imported')
        ->set('rows', [
            ['row' => 2, 'cells' => ['1111', '100', '']],
            ['row' => 3, 'cells' => ['1112', '', '']],
        ])
        ->call('save')->assertDispatched('excel-imported');

    expect(CashCount::query()->pluck('counted', 'account_code')->map(fn ($counted) => (string) $counted)->all())->toBe(['1111' => '100.00']);
});
