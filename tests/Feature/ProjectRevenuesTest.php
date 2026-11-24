<?php

use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\ProjectRevenuesScreen;
use App\Models\RevenueSheet;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\Entries\EntryException;
use App\Services\Entries\ProjectRevenues;
use App\Services\Ledger\VoucherApprovals;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    seedChartWithTestProjects();
    (require database_path('migrations/2026_11_25_110000_create_project_revenues.php'))->up();
});

function revUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

it('records a day\'s takings from the price list as one receipt voucher on project income, by kind', function () {
    $carl = revUser(RoleId::Cashier, 'Carl');
    $revenues = app(ProjectRevenues::class);
    $coffee = $revenues->saveItem(null, ['project' => 'F2F', 'name' => 'Coffee', 'kind' => 'sales', 'unit_price' => '1.50', 'currency' => 'USD']);

    $sheet = $revenues->record($carl, ['project' => 'F2F', 'day' => '2026-11-25', 'cash_account' => '1111', 'center' => 'Science Zone Aleppo'], [
        ['item' => (string) $coffee->id, 'quantity' => '10', 'unit_price' => ''],
        ['item' => '', 'description' => 'Photography course', 'kind' => 'course', 'quantity' => '2', 'unit_price' => '25'],
        ['item' => '', 'description' => '', 'quantity' => '1', 'unit_price' => ''],
    ]);

    $voucher = Voucher::query()->findOrFail($sheet->voucher_id);
    $lines = VoucherLine::query()->where('voucher_id', $voucher->id)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line) => [$line->account_code, $line->project_code, $line->debit_orig, $line->credit_orig])->all();

    expect($sheet->total)->toBe('65.00')
        ->and($voucher->type)->toBe(VoucherType::Receipt)
        ->and($lines)->toEqualCanonicalizing([
            ['4002', 'F2F', '0.00', '15.00'],
            ['4002', 'F2F', '0.00', '50.00'],
            ['1111', null, '65.00', '0.00'],
        ])
        ->and($revenues->summary(CarbonImmutable::parse('2026-11-01')))->toEqualCanonicalizing([
            ['project' => 'F2F', 'kind' => 'course', 'currency' => 'USD', 'total' => '50.00'],
            ['project' => 'F2F', 'kind' => 'sales', 'currency' => 'USD', 'total' => '15.00'],
        ]);

    // A rejected voucher takes the day out of the totals.
    app(VoucherApprovals::class)->reject($voucher, revUser(RoleId::FinancialManager, 'Fatima'), 'Count again');
    expect($sheet->refresh()->status)->toBe(RevenueSheet::REJECTED)
        ->and($revenues->summary(CarbonImmutable::parse('2026-11-01')))->toBe([]);
});

it('refuses takings with no line or an item priced in another currency', function () {
    $carl = revUser(RoleId::Cashier, 'Carl');
    $revenues = app(ProjectRevenues::class);
    $tea = $revenues->saveItem(null, ['project' => 'F2F', 'name' => 'Tea', 'kind' => 'sales', 'unit_price' => '5000', 'currency' => 'SYP']);

    expect(fn () => $revenues->record($carl, ['project' => 'F2F', 'day' => '2026-11-25', 'cash_account' => '1111'], []))->toThrow(EntryException::class)
        ->and(fn () => $revenues->record($carl, ['project' => 'F2F', 'day' => '2026-11-25', 'cash_account' => '1111'], [['item' => (string) $tea->id, 'quantity' => '1', 'unit_price' => '']]))
        ->toThrow(EntryException::class)
        ->and(RevenueSheet::query()->count())->toBe(0);
});

it('opens the revenues screen for the cashier and the accountant', function () {
    Livewire::actingAs(revUser(RoleId::Cashier, 'Carl'))->test(ProjectRevenuesScreen::class)
        ->assertSeeHtml('data-test="record-form"')
        ->set('tab', 'items')->assertSeeHtml('data-test="items"');

    $this->actingAs(revUser(RoleId::Employee, 'Emp'))->get(route('operations.revenues'))->assertForbidden();
});
