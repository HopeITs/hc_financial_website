<?php

use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\Purchases;
use App\Models\LedgerRequest;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Entries\EntryException;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Purchasing\PurchaseFiles;
use App\Services\Purchasing\PurchasePayments;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    (require database_path('migrations/2026_11_25_090000_create_gl_recurring_payments_table.php'))->up();
    (require database_path('migrations/2026_11_25_100000_create_payroll_tables.php'))->up();
    (require database_path('migrations/2026_11_25_110000_create_project_revenues.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-24 10:00:00');
});

function puUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

function puRequest(User $by, string $status = 'approved'): LedgerRequest
{
    return LedgerRequest::query()->create([
        'type' => LedgerRequest::EXTRA, 'project_code' => 'F2F', 'amount' => '5000.00', 'currency' => 'USD',
        'description' => 'Repairs', 'status' => $status, 'requested_by' => (int) $by->getAuthIdentifier(),
    ]);
}

/** A purchase of two lines on F2F's maintenance line: 10 × 50 and 1 × $second, in USD. */
function puPurchase(User $by, string $second = '100'): Purchase
{
    return app(PurchaseFiles::class)->create($by, [
        'request' => (string) puRequest($by)->id, 'kind' => Purchase::GOODS, 'title' => 'Paint and brushes', 'currency' => 'USD', 'location' => 'Aleppo',
    ], [
        ['budget_line' => 'F2F|Maintenance', 'description' => 'Paint', 'unit' => 'can', 'quantity' => '10', 'estimated_price' => '50'],
        ['budget_line' => 'F2F|Maintenance', 'description' => 'Brushes', 'unit' => 'pc', 'quantity' => '1', 'estimated_price' => $second],
    ]);
}

/** @param list<string> $prices */
function puQuote(User $by, Purchase $purchase, string $supplier, array $prices): void
{
    $items = $purchase->items()->get();
    $rows = [];
    foreach ($items as $index => $item) {
        $rows[$item->id] = ['price' => $prices[$index], 'brand' => ''];
    }

    app(PurchaseFiles::class)->addQuote($by, $purchase->refresh(), ['supplier' => $supplier, 'quoted_on' => '2026-11-24'], $rows, null);
}

it('buys from an approved request: quotes by value, the lowest bid, the order approved, received and paid on the budget lines', function () {
    $ann = puUser(RoleId::Accountant, 'Ann');
    $fatima = puUser(RoleId::FinancialManager, 'Fatima');
    $files = app(PurchaseFiles::class);
    $purchase = puPurchase($ann);

    // 600 USD estimated: two quotes.
    expect($files->quotesNeeded($purchase->refresh()))->toBe(2);

    puQuote($ann, $purchase, 'Al Nour', ['55', '90']);
    expect(fn () => $files->submitOrder($purchase->refresh(), $purchase->quotes()->first()->id, 'Lowest', ''))
        ->toThrow(EntryException::class);

    puQuote($ann, $purchase, 'Al Amal', ['48', '95']);
    $comparison = $files->comparison($purchase->refresh());
    $lowest = $purchase->quotes()->where('supplier', 'Al Amal')->sole();

    expect($comparison['lowest'])->toBe($lowest->id)
        ->and(array_column($comparison['quotes'], 'total'))->toBe(['640.00', '575.00']);

    $files->submitOrder($purchase->refresh(), $lowest->id, 'Lowest price', '25');
    expect($files->orderTotal($purchase->refresh()))->toBe('600.00')
        ->and($files->nextRole($purchase))->toBe(RoleId::FinancialManager)
        ->and($files->mayApprove($ann, $purchase))->toBeFalse();

    $files->approve($fatima, $purchase);
    expect($purchase->refresh()->stage)->toBe(Purchase::ORDERED)
        ->and($purchase->ordered_on->toDateString())->toBe('2026-11-24');

    $received = $purchase->items()->get()->mapWithKeys(fn ($item) => [$item->id => ['received_quantity' => (string) $item->quantity, 'condition' => 'Good']])->all();
    $files->receive($purchase, ['received_on' => '2026-11-26', 'invoice_number' => 'F-77', 'invoice_amount' => '610', 'accepted' => true], $received);

    // The invoice must be the order's total.
    expect(fn () => app(PurchasePayments::class)->pay($ann, $purchase->refresh(), ['method' => 'cash', 'date' => '2026-11-26', 'account' => '1111', 'center' => 'Science Zone Aleppo'], []))
        ->toThrow(EntryException::class);

    $files->receive($purchase->refresh(), ['received_on' => '2026-11-26', 'invoice_number' => 'F-77', 'invoice_amount' => '600', 'accepted' => true], $received);
    $voucher = app(PurchasePayments::class)->pay($ann, $purchase->refresh(), ['method' => 'cash', 'date' => '2026-11-26', 'account' => '1111', 'center' => 'Science Zone Aleppo'], []);

    $lines = VoucherLine::query()->where('voucher_id', $voucher->id)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line) => [$line->account_code, $line->budget_line_key, $line->debit_orig, $line->credit_orig])->all();

    expect($voucher->type)->toBe(VoucherType::Payment)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->request_id)->toBe($purchase->request_id)
        ->and($lines)->toBe([
            ['5405', 'F2F|Maintenance', '480.00', '0.00'],
            ['5405', 'F2F|Maintenance', '95.00', '0.00'],
            ['5405', 'F2F|Maintenance', '25.00', '0.00'],
            ['1111', null, '0.00', '600.00'],
        ])
        ->and($purchase->refresh()->stage)->toBe(Purchase::PAID)
        ->and($purchase->voucher_id)->toBe($voucher->id);

    // A rejected payment puts the file back to "received", to be paid again.
    app(VoucherApprovals::class)->reject(Voucher::query()->findOrFail($voucher->id), $fatima, 'Wrong safe');

    expect($purchase->refresh()->stage)->toBe(Purchase::RECEIVED)
        ->and($purchase->voucher_id)->toBeNull();
});

it('asks three quotes above 2,000 USD and the General Manager after the Financial Manager', function () {
    $ann = puUser(RoleId::Accountant, 'Ann');
    $files = app(PurchaseFiles::class);
    $purchase = puPurchase($ann, '2000');

    expect($files->quotesNeeded($purchase->refresh()))->toBe(3);

    puQuote($ann, $purchase, 'A', ['50', '2000']);
    puQuote($ann, $purchase, 'B', ['50', '2100']);
    expect(fn () => $files->submitOrder($purchase->refresh(), $purchase->quotes()->first()->id, 'Lowest', ''))->toThrow(EntryException::class);

    puQuote($ann, $purchase, 'C', ['55', '2050']);
    $files->submitOrder($purchase->refresh(), $purchase->quotes()->first()->id, 'Lowest', '');
    $files->approve(puUser(RoleId::FinancialManager, 'Fatima'), $purchase->refresh());

    expect($purchase->refresh()->stage)->toBe(Purchase::SUBMITTED)
        ->and($files->nextRole($purchase))->toBe(RoleId::GeneralManager);

    $files->approve(puUser(RoleId::GeneralManager, 'Gabriel'), $purchase);
    expect($purchase->refresh()->stage)->toBe(Purchase::ORDERED);
});

it('opens a purchase only on an approved request, with lines on its project\'s budget lines', function () {
    $ann = puUser(RoleId::Accountant, 'Ann');

    expect(fn () => app(PurchaseFiles::class)->create($ann, ['request' => (string) puRequest($ann, 'submitted')->id, 'kind' => 'goods', 'title' => 'Paint', 'currency' => 'USD'], [
        ['budget_line' => 'F2F|Maintenance', 'description' => 'Paint', 'quantity' => '1', 'estimated_price' => '5'],
    ]))->toThrow(EntryException::class);

    expect(fn () => app(PurchaseFiles::class)->create($ann, ['request' => (string) puRequest($ann)->id, 'kind' => 'goods', 'title' => 'Paint', 'currency' => 'USD'], [
        ['budget_line' => 'SZ|Nothing', 'description' => 'Paint', 'quantity' => '1', 'estimated_price' => '5'],
    ]))->toThrow(EntryException::class);

    expect(Purchase::query()->count())->toBe(0);
});

it('shows the purchase files screen and prints its forms', function () {
    $ann = puUser(RoleId::Accountant, 'Ann');
    $purchase = puPurchase($ann);
    puQuote($ann, $purchase, 'Al Amal', ['48', '95']);

    Livewire::actingAs($ann)->withQueryParams(['file' => (string) $purchase->id])->test(Purchases::class)
        ->assertSee('Paint and brushes')
        ->assertSeeHtml('data-test="comparison"')
        ->assertSeeHtml('data-test="print-cba"');

    foreach (['pr', 'rfq', 'cba'] as $form) {
        $this->actingAs($ann)->get(route('entries.purchases.print', ['purchase' => $purchase->id, 'form' => $form]))
            ->assertOk()->assertSee('Paint');
    }

    $this->actingAs(puUser(RoleId::Employee, 'Emp'))->get(route('entries.purchases'))->assertForbidden();
});
