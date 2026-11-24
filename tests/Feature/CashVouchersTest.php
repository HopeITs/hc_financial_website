<?php

use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\PaymentVoucher;
use App\Livewire\ReceiptVoucher;
use App\Models\LedgerRequest;
use App\Models\RequestLine;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Treasury\MonthlyRates;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    (require database_path('migrations/2026_11_25_090000_create_gl_recurring_payments_table.php'))->up();
    (require database_path('migrations/2026_11_25_100000_create_payroll_tables.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-19 10:00:00');
});

function cvUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

function cvMonthly(string $month, string $rate): void
{
    app(MonthlyRates::class)->set($month, 'SYP', $rate, null, User::query()->where('Username', 'Rates')->first() ?? cvUser(RoleId::FinancialManager, 'Rates'));
}

/** @return list<array{0: string, 1: string|null, 2: string|null, 3: string, 4: string, 5: string, 6: string}> account, project, budget line, debit, credit, debit USD, credit USD */
function cvLines(string $voucherId): array
{
    return VoucherLine::query()->where('voucher_id', $voucherId)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line): array => [$line->account_code, $line->project_code, $line->budget_line_key, $line->debit_orig, $line->credit_orig, $line->debit_usd, $line->credit_usd])
        ->all();
}

it('pays an invoice in pounds at the monthly rate, by project and budget line, the account coming from the budget line', function () {
    cvMonthly('2026-11', '13150');

    $page = Livewire::actingAs(cvUser(RoleId::Accountant, 'Ann'))->test(PaymentVoucher::class)
        ->assertSee('Payment voucher')
        ->assertSet('date', '2026-11-19')
        ->set('account', '1112')
        ->assertSet('rate', '13150')
        ->set('party', 'Al Nour bookshop')->set('reference', '245')
        ->set('lines.0.project', 'F2F')->set('lines.0.budget_line', 'F2F|Maintenance')
        ->assertSet('lines.0.account', '5405')
        ->set('lines.0.description', 'Paint')->set('lines.0.amount', '1,315,000')
        ->set('lines.1.project', 'F2F')->set('lines.1.budget_line', 'F2F|Bank charges')->set('lines.1.amount', '50000')
        ->assertSeeHtml('data-test="center"')
        ->assertSee('1,365,000.00')
        ->assertSee('103.80')
        ->set('center', 'Science Zone Aleppo')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('problems', []);

    $voucher = Voucher::query()->sole();
    $paint = VoucherLine::query()->where('voucher_id', $voucher->id)->where('line_no', 1)->sole();

    expect($voucher->type)->toBe(VoucherType::Payment)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->description)->toBe('Payment to Al Nour bookshop · No. 245')
        ->and(cvLines($voucher->id))->toBe([
            ['5405', 'F2F', 'F2F|Maintenance', '1315000.00', '0.00', '100.00', '0.00'],
            ['5501', 'F2F', 'F2F|Bank charges', '50000.00', '0.00', '3.80', '0.00'],
            ['1112', null, null, '0.00', '1365000.00', '0.00', '103.80'],
        ])
        ->and([$paint->rate, $paint->contact, $paint->center, $paint->reason, $paint->donor_currency, $paint->debit_donor])
        ->toBe(['13150.000000', 'Al Nour bookshop', 'Science Zone Aleppo', 'Paint', 'EUR', '86.96']);

    $page->assertSee("Voucher {$voucher->id} saved and waiting for approval.")
        ->assertSet('party', '')
        ->assertCount('lines', 2);
});

it('remembers the last cash account and project for the next voucher, and enters the last voucher again in one click', function () {
    cvMonthly('2026-11', '13150');
    $ann = cvUser(RoleId::Accountant, 'Ann');

    $page = Livewire::actingAs($ann)->test(PaymentVoucher::class)
        ->set('account', '1112')
        ->set('party', 'Al Nour bookshop')
        ->set('lines.0.project', 'F2F')->set('lines.0.budget_line', 'F2F|Maintenance')->set('lines.0.amount', '1315000')
        ->set('center', 'Science Zone Aleppo')
        ->call('save')
        ->assertSet('problems', [])
        ->assertSet('account', '1112')
        ->assertSet('rate', '13150')
        ->assertSet('lines.0.project', 'F2F')
        ->assertSet('party', '')
        ->assertSeeHtml('data-test="repeat-last"');

    $page->call('repeatLast')
        ->assertSet('party', 'Al Nour bookshop')
        ->assertSet('center', 'Science Zone Aleppo')
        ->assertCount('lines', 1)
        ->assertSet('lines.0.budget_line', 'F2F|Maintenance')
        ->assertSet('lines.0.amount', '1315000');

    Livewire::actingAs($ann)->test(PaymentVoucher::class)
        ->assertSet('account', '1112')
        ->assertSet('rate', '13150')
        ->assertSet('lines.0.project', 'F2F')
        ->assertSet('lines.1.project', 'F2F');

    Livewire::actingAs($ann)->test(ReceiptVoucher::class)
        ->assertSet('account', '')
        ->assertSet('lines.0.project', 'F2F');
});

it('takes the month before\'s rate until the month has one, keeps a typed rate, and a new project clears the budget line', function () {
    cvMonthly('2026-10', '13000');

    Livewire::actingAs(cvUser(RoleId::Accountant, 'Ann'))->test(PaymentVoucher::class)
        ->set('account', '1112')
        ->assertSet('rate', '13000')
        ->assertSee('Provisional: the rate of 10/2026 (13000)')
        ->set('rate', '13400')
        ->set('date', '2026-11-18')
        ->assertSet('rate', '13400')
        ->set('lines.0.project', 'F2F')->set('lines.0.budget_line', 'F2F|Maintenance')
        ->set('lines.0.project', 'SZ')
        ->assertSet('lines.0.budget_line', '');
});

it('says what is missing, and asks for an approved request above the limit', function () {
    $page = Livewire::actingAs(cvUser(RoleId::Accountant, 'Ann'))->test(PaymentVoucher::class)
        ->call('save')
        ->assertSee('Choose the account the money was paid from.')
        ->assertSee('Type who was paid.')
        ->assertSee('Add at least one line with an amount.')
        ->set('account', '1111')->set('party', 'Supplier')
        ->set('lines.0.amount', '50')
        ->call('save')
        ->assertSee('Line 1: choose a budget line or an account.')
        ->set('lines.0.project', 'F2F')->set('lines.0.budget_line', 'F2F|Maintenance')->set('lines.0.amount', '900')->set('center', 'SZ Aleppo')
        ->call('save')
        ->assertSee(__('ledger.rules.request_required', ['amount' => '900.00', 'limit' => '500.00']));

    expect(Voucher::query()->count())->toBe(0);

    $request = LedgerRequest::query()->create([
        'type' => 'extra', 'project_code' => 'F2F', 'amount' => '900', 'currency' => 'USD', 'status' => 'approved', 'requested_by' => 1,
    ]);
    RequestLine::query()->create(['request_id' => $request->id, 'budget_line_key' => 'F2F|Maintenance', 'amount' => '900', 'currency' => 'USD', 'rate' => 1, 'amount_usd' => '900', 'note' => 'Roof repair']);

    $page->set('lines', [])
        ->set('request', (string) $request->id)
        ->assertSet('lines.0.budget_line', 'F2F|Maintenance')
        ->assertSet('lines.0.account', '5405')
        ->assertSet('lines.0.amount', '900.00')
        ->call('save')
        ->assertSet('problems', []);

    expect(Voucher::query()->sole()->request_id)->toBe($request->id);
});

it('warns when a budget line reaches 80% of its budget', function () {
    // Bank charges: EUR 1,000 at the donor rate 1.15 is USD 1,150; USD 1,000 more is 86% of it.
    Livewire::actingAs(cvUser(RoleId::Accountant, 'Ann'))->test(PaymentVoucher::class)
        ->set('account', '1111')->set('party', 'Bank')
        ->set('lines.0.project', 'F2F')->set('lines.0.budget_line', 'F2F|Bank charges')->set('lines.0.amount', '400')
        ->assertDontSeeHtml('data-test="warnings"')
        ->set('lines.0.amount', '1000')
        ->assertSee('The budget line "Bank charges" of F2F reaches 86% of its budget with this voucher.');
});

it('receives money into a cash account, the line crediting the account chosen', function () {
    Livewire::actingAs(cvUser(RoleId::Accountant, 'Ann'))->test(ReceiptVoucher::class)
        ->assertSee('Receipt voucher')
        ->set('account', '1111')->set('party', 'CDCS')
        ->set('lines.0.project', 'SZ')->set('lines.0.account', '4001')->set('lines.0.amount', '200')
        ->call('save')
        ->assertSet('problems', []);

    $voucher = Voucher::query()->sole();

    expect($voucher->type)->toBe(VoucherType::Receipt)
        ->and(cvLines($voucher->id))->toBe([
            ['4001', 'SZ', null, '0.00', '200.00', '0.00', '200.00'],
            ['1111', null, null, '200.00', '0.00', '200.00', '0.00'],
        ]);
});

it('opens the entry screens for the preparing roles only', function () {
    $this->actingAs(cvUser(RoleId::Accountant, 'Ann'))->get('/entries/payment')->assertOk()->assertSeeHtml('data-test="voucher-form"');
    $this->get('/entries/receipt')->assertOk();

    $this->actingAs(cvUser(RoleId::GeneralManager, 'Gina'))->get('/entries/payment')->assertForbidden();
    Livewire::actingAs(cvUser(RoleId::FinancialManager, 'Fadi'))->test(PaymentVoucher::class)
        ->assertDontSeeHtml('data-test="save-voucher"')
        ->call('save')
        ->assertForbidden();
});
