<?php

use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\PointOfSale as PointOfSaleScreen;
use App\Livewire\ProjectRevenuesScreen;
use App\Models\RevenueSheet;
use App\Models\RevenueTicket;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\Entries\EntryException;
use App\Services\Entries\PointOfSale;
use App\Services\Entries\ProjectRevenues;
use App\Services\Ledger\VoucherApprovals;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    seedChartWithTestProjects();
    (require database_path('migrations/2026_11_25_110000_create_project_revenues.php'))->up();
    (require database_path('migrations/2026_12_01_090000_add_point_of_sale_to_project_revenues.php'))->up();
    (require database_path('migrations/2026_12_02_090000_add_orders_to_point_of_sale.php'))->up();
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

it('runs tables like a restaurant: orders filled over time, paid with a discount, and the shift booked as one voucher', function () {
    $carl = revUser(RoleId::Cashier, 'Carl');
    $revenues = app(ProjectRevenues::class);
    $pos = app(PointOfSale::class);
    $coffee = $revenues->saveItem(null, ['project' => 'F2F', 'code' => 'C1', 'name' => 'Coffee', 'kind' => 'sales', 'category' => 'Hot drinks', 'unit_price' => '2', 'currency' => 'USD']);
    $course = $revenues->saveItem(null, ['project' => 'F2F', 'name' => 'Course', 'kind' => 'course', 'unit_price' => '20', 'currency' => 'USD']);

    $shift = $pos->openShift($carl, ['project' => 'F2F', 'cash_account' => '1111', 'day' => '2026-11-27']);
    $table = $pos->openOrder($carl, $shift, ['table' => '5', 'persons' => '2', 'waiter' => 'Sami']);
    expect($pos->openShift($carl, ['project' => 'F2F', 'cash_account' => '1111', 'day' => '2026-11-27'])->id)->toBe($shift->id)
        ->and($pos->openOrder($carl, $shift, ['table' => '5'])->id)->toBe($table->id)
        ->and(fn () => $pos->openOrder($carl, $shift, ['service' => 'hall']))->toThrow(EntryException::class)
        ->and($pos->readQuickEntry($shift, '2*C1'))->sequence(fn ($item) => $item->id->toBe($coffee->id), fn ($quantity) => $quantity->toBe('2.00'));

    // Items come in over time: the second coffee joins its line. 4 coffees and a course is 28; 10% off is 2.80.
    $pos->addItems($table, [$coffee->id => '3', $course->id => '1']);
    $pos->addItems($table, [$coffee->id => '1']);
    $table = $pos->updateOrder($table, ['table' => '5', 'service' => 'hall', 'discount_percent' => '10', 'payment_method' => 'cash']);
    expect($table->lines()->count())->toBe(2)
        ->and($table->net)->toBe('25.20')
        ->and(fn () => $pos->updateOrder($table, ['table' => '5', 'discount' => '50']))->toThrow(EntryException::class)
        ->and(fn () => $pos->closeShift($carl, $shift))->toThrow(EntryException::class)
        ->and(fn () => $pos->pay($table, '20'))->toThrow(EntryException::class);

    $paid = $pos->pay($table, '30');
    expect($paid->status)->toBe('paid')
        ->and($paid->lines->pluck('amount')->all())->toBe(['7.20', '18.00'])
        ->and(fn () => $pos->addItems($paid, [$coffee->id => '1']))->toThrow(EntryException::class);

    // A wrong take-away is cancelled after it was paid: it leaves the shift's total.
    $wrong = $pos->openOrder($carl, $shift, ['service' => 'takeaway']);
    $pos->pay($pos->addItems($wrong, [$coffee->id => '1']));
    expect($shift->refresh()->total)->toBe('27.20');
    $pos->cancel($wrong->refresh());

    $closed = $pos->closeShift($carl, $shift->refresh());
    $lines = VoucherLine::query()->where('voucher_id', $closed->voucher_id)->get()
        ->map(fn (VoucherLine $line) => [$line->account_code, $line->debit_orig, $line->credit_orig])->all();
    expect($closed->status)->toBe(RevenueSheet::POSTED)
        ->and($closed->total)->toBe('25.20')
        ->and($lines)->toEqualCanonicalizing([['4002', '0.00', '7.20'], ['4002', '0.00', '18.00'], ['1111', '25.20', '0.00']])
        ->and($pos->report($closed)['net'])->toBe('25.20');
});

it('works a table on the full-screen point of sale: open, add items, close and print', function () {
    $carl = revUser(RoleId::Cashier, 'Carl');
    $coffee = app(ProjectRevenues::class)->saveItem(null, ['project' => 'F2F', 'name' => 'Coffee', 'kind' => 'sales', 'unit_price' => '2', 'currency' => 'USD']);

    $page = Livewire::actingAs($carl)->test(PointOfSaleScreen::class)
        ->assertSeeHtml('data-test="pos-open"')
        ->set('opening.project', 'F2F')->set('opening.cash_account', '1111')->call('openShift')
        ->assertSeeHtml('data-test="pos-tables"')
        ->set('table.table', '7')->call('openTable')
        ->assertSet('adding', true)
        ->assertSeeHtml('data-test="pos-tile-'.$coffee->id.'"')
        ->call('tapItem', $coffee->id)->call('tapItem', $coffee->id)
        ->assertSet('pending', [$coffee->id => '2'])
        ->call('savePending')
        ->assertSet('adding', false)
        ->assertSeeHtml('data-test="pos-lines"');

    $order = RevenueTicket::query()->sole();
    $this->actingAs($carl)->get(route('operations.revenues.pos.receipt', ['order' => $order->id]))->assertOk()->assertSee('Coffee');

    $page->call('startPaying')->set('paid', '5')->assertSeeHtml('data-test="pos-change"')
        ->call('pay')->assertSet('problems', [])->assertSet('orderId', null);
    expect(RevenueSheet::query()->sole()->total)->toBe('4.00')
        ->and($order->refresh()->printed_at)->not->toBeNull();

    // Another cashier does not see Carl's shift.
    Livewire::actingAs(revUser(RoleId::Cashier, 'Cleo'))->test(PointOfSaleScreen::class)->assertDontSeeHtml('data-test="pos-resume-');

    $this->actingAs($carl)->get(route('operations.revenues.pos.report', ['shift' => $order->sheet_id]))->assertOk();
    $page->set('closing', true)->call('closeShift')->assertSet('shiftId', null);
    $this->actingAs(revUser(RoleId::Employee, 'Emp'))->get(route('operations.revenues.pos'))->assertForbidden();
});

it('opens the revenues screen for the cashier and the accountant', function () {
    Livewire::actingAs(revUser(RoleId::Cashier, 'Carl'))->test(ProjectRevenuesScreen::class)
        ->assertSeeHtml('data-test="record-form"')
        ->assertSeeHtml('data-test="open-pos"')
        ->set('tab', 'items')->assertSeeHtml('data-test="items"');

    $this->actingAs(revUser(RoleId::Employee, 'Emp'))->get(route('operations.revenues'))->assertForbidden();
});
