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
    (require database_path('migrations/2026_12_01_090000_add_point_of_sale_to_project_revenues.php'))->up();
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

it('sells from an open shift with a discount, cancels a wrong sale and books the shift as one voucher', function () {
    $carl = revUser(RoleId::Cashier, 'Carl');
    $revenues = app(ProjectRevenues::class);
    $coffee = $revenues->saveItem(null, ['project' => 'F2F', 'name' => 'Coffee', 'kind' => 'sales', 'category' => 'Hot drinks', 'unit_price' => '2', 'currency' => 'USD']);
    $course = $revenues->saveItem(null, ['project' => 'F2F', 'name' => 'Course', 'kind' => 'course', 'unit_price' => '20', 'currency' => 'USD']);

    $shift = $revenues->openShift($carl, ['project' => 'F2F', 'cash_account' => '1111', 'day' => '2026-11-26']);
    expect($revenues->openShift($carl, ['project' => 'F2F', 'cash_account' => '1111', 'day' => '2026-11-26'])->id)->toBe($shift->id)
        ->and($coffee->category)->toBe('Hot drinks');

    // 3 coffees and a course is 26; 10% off is 2.60, shared by each line's part of the sale.
    $first = $revenues->sell($carl, $shift, [$coffee->id => '3', $course->id => '1'], ['discount_percent' => '10', 'paid' => '30']);
    $wrong = $revenues->sell($carl, $shift, [$coffee->id => '1'], []);
    expect($first->net)->toBe('23.40')
        ->and($first->lines->pluck('amount')->all())->toBe(['5.40', '18.00'])
        ->and($shift->refresh()->total)->toBe('25.40')
        ->and(fn () => $revenues->sell($carl, $shift, [$coffee->id => '1'], ['discount' => '5']))->toThrow(EntryException::class)
        ->and(fn () => $revenues->sell($carl, $shift, [$coffee->id => '1'], ['paid' => '1']))->toThrow(EntryException::class);

    $revenues->voidTicket($wrong);
    $closed = $revenues->closeShift($carl, $shift->refresh());

    $lines = VoucherLine::query()->where('voucher_id', $closed->voucher_id)->get()
        ->map(fn (VoucherLine $line) => [$line->account_code, $line->debit_orig, $line->credit_orig])->all();
    expect($closed->status)->toBe(RevenueSheet::POSTED)
        ->and($closed->total)->toBe('23.40')
        ->and($lines)->toEqualCanonicalizing([['4002', '0.00', '5.40'], ['4002', '0.00', '18.00'], ['1111', '23.40', '0.00']])
        ->and(fn () => $revenues->sell($carl, $closed, [$coffee->id => '1']))->toThrow(EntryException::class);
});

it('runs a sale on the point-of-sale screen, tile by tile', function () {
    $carl = revUser(RoleId::Cashier, 'Carl');
    $coffee = app(ProjectRevenues::class)->saveItem(null, ['project' => 'F2F', 'name' => 'Coffee', 'kind' => 'sales', 'unit_price' => '2', 'currency' => 'USD']);

    $page = Livewire::actingAs($carl)->test(ProjectRevenuesScreen::class)
        ->assertSet('tab', 'pos')
        ->assertSeeHtml('data-test="pos-open"')
        ->set('opening.project', 'F2F')->set('opening.cash_account', '1111')->call('openShift')
        ->assertSeeHtml('data-test="pos-tile-'.$coffee->id.'"')
        ->call('addItem', $coffee->id)->call('addItem', $coffee->id)
        ->assertSet('basket', [$coffee->id => '2'])
        ->set('payment.paid', '5')->assertSeeHtml('data-test="pos-change"')
        ->call('sell')
        ->assertSet('problems', [])->assertSet('basket', []);

    expect(RevenueSheet::query()->sole()->total)->toBe('4.00');

    // Another cashier does not see Carl's shift.
    Livewire::actingAs(revUser(RoleId::Cashier, 'Cleo'))->test(ProjectRevenuesScreen::class)
        ->assertDontSeeHtml('data-test="pos-resume-');
    $page->set('closing', true)->call('closeShift')->assertSet('shiftId', null);
});

it('opens the revenues screen for the cashier and the accountant', function () {
    Livewire::actingAs(revUser(RoleId::Cashier, 'Carl'))->test(ProjectRevenuesScreen::class)
        ->set('tab', 'record')
        ->assertSeeHtml('data-test="record-form"')
        ->set('tab', 'items')->assertSeeHtml('data-test="items"');

    $this->actingAs(revUser(RoleId::Employee, 'Emp'))->get(route('operations.revenues'))->assertForbidden();
});
