<?php

use App\Enums\RoleId;
use App\Livewire\Donors;
use App\Livewire\ExcelTools;
use App\Livewire\ExchangeRates;
use App\Models\Contract;
use App\Models\DailyRate;
use App\Models\Donor;
use App\Models\MonthlyRate;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Excel\Datasets\DonorsExcel;
use App\Services\Excel\ExcelSheet;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\MonthlyRates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-20 10:00:00');
});

function mdUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** @return list<array{row: int, cells: list<string>}> rows as the sheet reader gives them, from row 2 */
function mdRows(array $cells): array
{
    return array_map(fn (array $row, int $index): array => ['row' => $index + 2, 'cells' => $row], $cells, array_keys($cells));
}

it('lets the proposer take back a waiting rate and the Financial Manager remove an approved one, which then gives way to the rate before it', function () {
    $ann = mdUser(RoleId::Accountant, 'Ann');
    $fadi = mdUser(RoleId::FinancialManager, 'Fadi');
    $rates = app(DailyRates::class);
    $earlier = $rates->propose('EUR', '2026-11-18', '1.15', null, $fadi, approveNow: true);
    $today = $rates->propose('EUR', '2026-11-20', '1.17', null, $fadi, approveNow: true);
    $mine = $rates->propose('EUR', '2026-11-20', '1.18', null, $ann, approveNow: false);

    Livewire::actingAs($ann)->test(ExchangeRates::class)
        ->call('removeDaily', $today->id)->assertSee(__('treasury.rate_remove_not_allowed'))
        ->call('removeDaily', $mine->id)->assertSee(__('treasury.rate_removed'));

    Livewire::actingAs($fadi)->test(ExchangeRates::class)
        ->call('editDaily', $today->id)->assertSet('rate', '1.17')->assertSet('day', '2026-11-20')
        ->call('removeDaily', $today->id)->assertSee(__('treasury.rate_removed'));

    expect($mine->refresh()->status)->toBe(DailyRate::REMOVED)
        ->and($today->refresh()->status)->toBe(DailyRate::REMOVED)
        ->and($rates->rateFor('EUR', CarbonImmutable::today())?->id)->toBe($earlier->id);
});

it('lets the Financial Manager change and delete a monthly rate', function () {
    $fadi = mdUser(RoleId::FinancialManager, 'Fadi');
    $october = app(MonthlyRates::class)->set('2026-10', 'SYP', '13000', null, $fadi);
    $november = app(MonthlyRates::class)->set('2026-11', 'SYP', '13500', null, $fadi);

    Livewire::actingAs($fadi)->test(ExchangeRates::class)
        ->call('editMonthly', $november->id)->assertSet('month', '2026-11')->assertSet('monthlyRate', '13500')
        ->call('removeMonthly', $november->id)->assertSee(__('treasury.monthly_removed'));

    expect(MonthlyRate::query()->find($november->id))->toBeNull()
        ->and(app(MonthlyRates::class)->forDay('SYP', CarbonImmutable::today()))->toMatchArray(['rate' => '13000.000000', 'provisional' => true]);

    Livewire::actingAs(mdUser(RoleId::Accountant, 'Ann'))->test(ExchangeRates::class)->call('removeMonthly', $october->id)->assertForbidden();
});

it('imports daily rates all together: approved for the Financial Manager, waiting for the accountant, and none while one row is wrong', function () {
    $rows = mdRows([['EUR', '2026-11-19', '1.16', ''], ['EUR', '2026-11-20', '1,17', 'From the bank']]);

    Livewire::actingAs(mdUser(RoleId::Accountant, 'Ann'))->test(ExcelTools::class, ['kind' => 'daily_rates'])
        ->call('openImport')
        ->set('rows', [...$rows, ['row' => 4, 'cells' => ['XYZ', '2026-13-01', '0', '']]])
        ->assertSeeHtml('data-test="excel-row-4"')
        ->assertSee(__('excel.bad_day'))
        ->call('save')
        ->assertSee(trans_choice('excel.not_saved', 1, ['count' => 1]));

    expect(DailyRate::query()->count())->toBe(0);

    Livewire::actingAs(mdUser(RoleId::Accountant, 'Ben'))->test(ExcelTools::class, ['kind' => 'daily_rates'])
        ->set('rows', $rows)->call('save')->assertDispatched('excel-imported');

    expect(DailyRate::query()->where('status', DailyRate::PENDING)->count())->toBe(2);

    Livewire::actingAs(mdUser(RoleId::FinancialManager, 'Fadi'))->test(ExcelTools::class, ['kind' => 'daily_rates'])
        ->set('rows', $rows)->call('save')->assertDispatched('excel-imported');

    expect(DailyRate::query()->where('status', DailyRate::APPROVED)->pluck('rate', 'day')->map(fn ($rate) => (string) $rate)->values()->all())->toBe(['1.160000', '1.170000']);
});

it('imports monthly rates only for the Financial Manager, and exports them in the columns the import reads', function () {
    $fadi = mdUser(RoleId::FinancialManager, 'Fadi');

    Livewire::actingAs(mdUser(RoleId::Accountant, 'Ann'))->test(ExcelTools::class, ['kind' => 'monthly_rates'])->call('openImport')->assertForbidden();

    Livewire::actingAs($fadi)->test(ExcelTools::class, ['kind' => 'monthly_rates'])
        ->set('rows', mdRows([['2026-11', 'SYP', '13500', '']]))->call('save')->assertDispatched('excel-imported');

    Livewire::actingAs($fadi)->test(ExcelTools::class, ['kind' => 'monthly_rates'])->call('export')->assertFileDownloaded('monthly-rates-2026-11-20.xlsx');

    expect(MonthlyRate::query()->sole())->toMatchArray(['month' => '2026-11', 'currency' => 'SYP']);
});

it('deletes a donor with no agreement and refuses one that has agreements', function () {
    $fadi = mdUser(RoleId::FinancialManager, 'Fadi');
    $free = Donor::query()->create(['code' => 'FREE', 'name_en' => 'Free donor', 'name_ar' => 'مانح', 'type' => 'foundation', 'is_active' => true]);
    $bound = Donor::query()->create(['code' => 'BOUND', 'name_en' => 'Bound donor', 'name_ar' => 'مانح', 'type' => 'foundation', 'is_active' => true]);
    Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => $bound->id, 'title' => 'Education', 'signed_on' => '2026-03-01', 'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28',
        'currency' => 'USD', 'amount' => '1000', 'fixed_rate' => '1', 'restriction' => 'restricted', 'status' => 'draft', 'created_by' => 1,
    ]);

    Livewire::actingAs($fadi)->test(Donors::class)
        ->assertSeeHtml('data-test="delete-donor-FREE"')
        ->call('delete', $free->id)->assertSee('Donor Free donor deleted.')
        ->call('delete', $bound->id)->assertSee(__('donors.delete_has_agreements', ['name' => 'Bound donor']));

    expect(Donor::query()->pluck('code')->all())->toBe(['BOUND']);

    Livewire::actingAs(mdUser(RoleId::Accountant, 'Ann'))->test(Donors::class)->call('delete', $bound->id)->assertForbidden();
});

it('imports donors: a new code adds one, a known code changes it, a wrong row stops all', function () {
    $fadi = mdUser(RoleId::FinancialManager, 'Fadi');
    Donor::query()->create(['code' => 'CARPL', 'name_en' => 'Caritas', 'name_ar' => 'كاريتاس', 'type' => 'church_agency', 'is_active' => true]);
    $rows = mdRows([
        ['CARPL', 'Caritas Poland', 'كاريتاس بولندا', 'Church agency', 'Poland', 'EUR', '', '', '', 'yes', ''],
        ['NEWFD', 'New Foundation', 'مؤسسة جديدة', 'foundation', '', '', 'Sam', 'sam@example.org', '', 'no', 'First year'],
    ]);

    $page = Livewire::actingAs($fadi)->test(ExcelTools::class, ['kind' => 'donors'])->set('rows', [...$rows, ['row' => 4, 'cells' => ['BAD CODE', '', 'x', 'alien', '', 'ZZZ', '', 'not-an-email', '', 'maybe', '']]]);
    $reviewed = app(DonorsExcel::class)->review($page->get('rows'), $fadi, []);

    expect(array_column($reviewed, 'action'))->toBe(['update', 'new', 'new'])
        ->and($reviewed[2]['problems'])->toContain(__('excel.bad_donor_type'))
        ->and($reviewed[2]['problems'])->toContain(__('excel.bad_yes_no', ['column' => __('excel.col.active')]));

    $page->call('save')->assertNotDispatched('excel-imported');
    expect(Donor::query()->count())->toBe(1);

    Livewire::actingAs($fadi)->test(ExcelTools::class, ['kind' => 'donors'])->set('rows', $rows)->call('save')->assertDispatched('excel-imported');

    expect(Donor::query()->where('code', 'CARPL')->sole())->toMatchArray(['name_en' => 'Caritas Poland', 'country' => 'Poland', 'currency' => 'EUR', 'is_active' => true])
        ->and(Donor::query()->where('code', 'NEWFD')->sole())->toMatchArray(['type' => 'foundation', 'is_active' => false, 'contact_email' => 'sam@example.org']);
});

it('writes a donors template the import reads back, with the donor types beside it', function () {
    $fadi = mdUser(RoleId::FinancialManager, 'Fadi');
    Livewire::actingAs($fadi)->test(ExcelTools::class, ['kind' => 'donors'])->call('template')->assertFileDownloaded('donors-template.xlsx');

    $dataset = app(DonorsExcel::class);
    $path = app(ExcelSheet::class)->write([
        'Donors' => ['headings' => $dataset->headings(), 'rows' => $dataset->exampleRows([])],
        ...$dataset->referenceSheets([]),
    ], false);
    $read = app(ExcelSheet::class)->read($path, count($dataset->columns()), 100);

    expect($read)->toHaveCount(1)
        ->and($dataset->review($read, $fadi, [])[0]['problems'])->toBe([]);
});
