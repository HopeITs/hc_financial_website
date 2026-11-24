<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Livewire\ApprovalsInboxScreen;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Home\ApprovalsInbox;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    DB::connection('legacy')->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    (require database_path('migrations/2026_11_25_100000_create_payroll_tables.php'))->up();
    seedChartWithTestProjects();
});

function aiUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function aiVoucher(User $by): Voucher
{
    return app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => (int) $by->getAuthIdentifier()], [
        new LineDraft('1111', 'USD', debit: 100, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4001', 'USD', credit: 100, projectCode: 'F2F'),
    ]));
}

it('lists what waits for the Financial Manager and approves it with one click, through its own rules', function () {
    $ann = aiUser(RoleId::Accountant, 'Ann');
    $fatima = aiUser(RoleId::FinancialManager, 'Fatima');
    $first = aiVoucher($ann);
    $second = aiVoucher($ann);
    $own = aiVoucher($fatima);

    $inbox = app(ApprovalsInbox::class)->for($fatima);
    expect(array_column($inbox['vouchers'], 'id'))->toEqualCanonicalizing([$first->id, $second->id])
        ->and($inbox['requests'])->toBe([]);

    Livewire::actingAs($fatima)->test(ApprovalsInboxScreen::class)
        ->assertSeeHtml('data-test="kind-vouchers"')
        ->call('approve', 'vouchers', $first->id)
        ->assertSet('problems', [])
        ->call('approveAll', 'vouchers')
        ->assertSee('1 item approved.');

    expect($first->fresh()->status)->toBe(VoucherStatus::Approved)
        ->and($second->fresh()->status)->toBe(VoucherStatus::Approved)
        ->and($own->fresh()->status)->toBe(VoucherStatus::PendingApproval);
});

it('opens for those who approve and not for the others', function () {
    $this->actingAs(aiUser(RoleId::Accountant, 'Ann'))->get(route('inbox'))->assertForbidden();
    $this->actingAs(aiUser(RoleId::GeneralManager, 'Gabriel'))->get(route('inbox'))->assertOk();
});
