<?php

use App\Enums\RoleId;
use App\Livewire\OperationsHome;
use App\Models\LedgerRequest;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Navigation;
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
});

function opUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

it('shows the operations in one place, each card only to those who may open it, with what is on its way', function () {
    $ann = opUser(RoleId::Accountant, 'Ann');
    $request = LedgerRequest::query()->create(['type' => 'extra', 'project_code' => 'F2F', 'amount' => '100', 'currency' => 'USD', 'status' => 'approved', 'requested_by' => (int) $ann->getAuthIdentifier()]);
    Purchase::query()->create(['request_id' => $request->id, 'project_code' => 'F2F', 'title' => 'Paint', 'created_by' => (int) $ann->getAuthIdentifier()]);

    Livewire::actingAs($ann)->test(OperationsHome::class)
        ->assertSeeHtml('data-test="section-spending"')
        ->assertSeeHtml('data-test="card-purchases"')
        ->assertSeeHtml('data-test="count-purchases"')
        ->assertSeeHtml('data-test="card-revenues"')
        ->assertSeeHtml('data-test="card-other_receipts"')
        ->assertSeeHtml('data-test="card-transfers"');

    Livewire::actingAs(opUser(RoleId::Cashier, 'Carl'))->test(OperationsHome::class)
        ->assertSeeHtml('data-test="card-advances"')
        ->assertDontSeeHtml('data-test="card-transfers"');

    $this->actingAs(opUser(RoleId::Employee, 'Emp'))->get(route('operations'))->assertForbidden();
});

it('keeps the payment and receipt vouchers out of the menu, still open from the operations', function () {
    $ann = opUser(RoleId::Accountant, 'Ann');

    expect(collect(Navigation::visibleGroups($ann))->flatten())->not->toContain('single')->not->toContain('receipt')
        ->and(Navigation::visibleGroups($ann)['operations'])->toContain('purchases');

    $this->actingAs($ann)->get(route(Navigation::routeName('single')))->assertOk();
    $this->actingAs($ann)->get(route(Navigation::routeName('receipt')))->assertOk();
});
