<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\Vouchers;
use App\Models\MonthClose;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();

    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    Schema::connection('legacy')->create('role_currency_limit', function ($table) {
        $table->integer('Role_ID');
        $table->integer('TillType_ID');
        $table->decimal('Approval_Limit', 18, 2);
    });
    DB::connection('legacy')->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);
    DB::connection('legacy')->table('role_currency_limit')->insert(['Role_ID' => RoleId::FinancialManager->value, 'TillType_ID' => 2, 'Approval_Limit' => 1000]);

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function screenUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function screenVoucher(User $by, array $header = [], int $amount = 100, ?array $lines = null): Voucher
{
    return app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => (int) $by->getAuthIdentifier()] + $header, $lines ?? [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: 'F2F'),
    ]));
}

function vouchersPage(User $user, array $query = [])
{
    return Livewire::actingAs($user)->withQueryParams($query)->test(Vouchers::class);
}

it('lists the vouchers with their number, type, amount, preparer and status', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $voucher = screenVoucher($ann, ['description' => 'ACN first instalment'], 250);

    vouchersPage(screenUser(RoleId::FinancialManager, 'Fatima'))
        ->assertSeeInOrder([$voucher->id, '2026-03-10', 'CRV', 'ACN first instalment', '250.00', 'Pending approval'])
        ->assertSee('Ann');
});

it('filters by type chip, status, date range and search', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $receipt = screenVoucher($ann, ['description' => 'January grant', 'date' => CarbonImmutable::parse('2026-01-10')]);
    $payment = screenVoucher($ann, ['description' => 'Rent top-up', 'type' => VoucherType::Payment, 'date' => CarbonImmutable::parse('2026-03-20')], 50, [
        new LineDraft('1121', 'USD', debit: 50, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('1111', 'USD', credit: 50, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);
    $payment->update(['status' => VoucherStatus::Approved]);

    vouchersPage($manager)->call('setType', 'CPV')->assertSee('Rent top-up')->assertDontSee('January grant')
        ->call('setType', '')->assertSee('January grant')->assertSee('Rent top-up')
        ->set('status', 'approved')->assertSee('Rent top-up')->assertDontSee('January grant')
        ->set('status', '')->set('from', '2026-03-01')->assertSee('Rent top-up')->assertDontSee('January grant')
        ->set('from', '')->set('to', '2026-02-01')->assertSee('January grant')->assertDontSee('Rent top-up')
        ->set('to', '')->set('search', $receipt->id)->assertSee('January grant')->assertDontSee('Rent top-up')
        ->set('search', 'rent')->assertSee('Rent top-up')->assertDontSee('January grant')
        ->set('search', 'nothing like this')->assertSeeHtml('data-test="no-vouchers"');
});

it('shows the pending count in the menu until nothing is pending', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $voucher = screenVoucher($ann);
    screenVoucher($ann);

    $this->actingAs($manager)->get(route('entries.vouchers'))->assertOk()->assertSeeHtml('data-test="badge-vouchers"');

    Voucher::query()->update(['status' => VoucherStatus::Approved]);

    $this->actingAs($manager)->get(route('entries.vouchers'))->assertOk()->assertDontSeeHtml('data-test="badge-vouchers"');
    expect($voucher->fresh()->status)->toBe(VoucherStatus::Approved);
});

it('opens a voucher from its link: lines, totals, attachments and history', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $voucher = screenVoucher($ann, ['description' => 'ACN first instalment'], 250);
    VoucherAttachment::query()->create(['voucher_id' => $voucher->id, 'original_name' => 'bank-slip.pdf', 'stored_path' => 'a/b.pdf', 'size' => 2048]);

    $this->actingAs(screenUser(RoleId::Accountant, 'Omar'))->get(route('entries.vouchers', ['voucher' => $voucher->id]))->assertOk()->assertSee($voucher->id);

    vouchersPage(screenUser(RoleId::FinancialManager, 'Fatima'), ['voucher' => $voucher->id])
        ->assertSeeHtml('data-test="voucher-lines"')
        ->assertSeeInOrder(['1111', 'Aleppo main safe', 'F2F', '250.00'])
        ->assertSee('4101')
        ->assertSee('bank-slip.pdf')->assertSee('2.0 KB')
        ->assertSeeInOrder(['History', 'Ann', 'Prepared'])
        ->assertSee('Pending approval');
});

it('lets the financial manager approve, and records who and when', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $voucher = screenVoucher($ann);

    vouchersPage($manager, ['voucher' => $voucher->id])->assertSeeHtml('data-test="approve"')
        ->call('approve')
        ->assertSee(__('vouchers.approved_notice', ['number' => $voucher->id]))
        ->assertSeeHtml('data-test="voucher-status"')->assertSee('Approved')
        ->assertDontSeeHtml('data-test="approval-actions"');

    expect($voucher->fresh()->approved_by)->toBe((int) $manager->getAuthIdentifier());
});

it('does not let the preparer approve or reject their own voucher', function () {
    $head = screenUser(RoleId::HeadOffice, 'Hana');
    $voucher = screenVoucher($head);

    vouchersPage($head, ['voucher' => $voucher->id])
        ->assertSeeHtml('data-test="own-note"')
        ->call('approve')->assertSee(__('ledger.rules.approver_not_preparer'))
        ->call('openReject')->set('rejectReason', 'Mine')->call('reject')->assertSee(__('ledger.rules.reject_own'));

    expect($voucher->fresh()->status)->toBe(VoucherStatus::PendingApproval)
        ->and(Voucher::query()->count())->toBe(1);
});

it('refuses to approve above the approval limit and says so', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $voucher = screenVoucher($ann, amount: 1500);

    vouchersPage($manager, ['voucher' => $voucher->id])
        ->assertSeeHtml('data-test="limit-note"')
        ->call('approve')->assertSee(__('ledger.rules.approval_not_allowed'));

    expect($voucher->fresh()->status)->toBe(VoucherStatus::PendingApproval);
});

it('refuses to approve into a closed month', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $voucher = screenVoucher($ann);
    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);

    vouchersPage($manager, ['voucher' => $voucher->id])->call('approve')
        ->assertSee(__('ledger.rules.month_closed', ['month' => '2026-03']))
        ->assertSeeHtml('data-test="failures"');

    expect($voucher->fresh()->status)->toBe(VoucherStatus::PendingApproval);
});

it('rejects with a reason: the voucher keeps its number, shows as rejected with the reason, and needs a reason', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $voucher = screenVoucher($ann);

    $page = vouchersPage($manager, ['voucher' => $voucher->id])->call('openReject')
        ->set('rejectReason', '   ')->call('reject')->assertHasErrors(['rejectReason']);

    expect(Voucher::query()->find($voucher->id))->not->toBeNull();

    $page->set('rejectReason', 'Wrong project')->call('reject')->assertHasNoErrors()
        ->assertSee(__('vouchers.rejected_notice', ['number' => $voucher->id]))
        ->assertSeeHtml('data-test="rejection"')->assertSee('Wrong project')->assertSee('Rejected');

    expect(Voucher::query()->find($voucher->id))->toBeNull()
        ->and(Voucher::onlyTrashed()->find($voucher->id))->not->toBeNull();

    vouchersPage($manager)->assertDontSee($voucher->id)
        ->set('status', 'rejected')->assertSee($voucher->id)->assertSee('Rejected');
});

it('lets the accountant read vouchers but not approve or reject them', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $omar = screenUser(RoleId::Accountant, 'Omar');
    $voucher = screenVoucher($ann);

    vouchersPage($omar, ['voucher' => $voucher->id])->assertOk()->assertDontSeeHtml('data-test="approval-actions"')
        ->call('approve')->assertForbidden();
    vouchersPage($omar, ['voucher' => $voucher->id])->call('openReject')->assertForbidden();

    expect($voucher->fresh()->status)->toBe(VoucherStatus::PendingApproval);
});

it('is closed to the roles that cannot see vouchers', function () {
    $this->actingAs(screenUser(RoleId::Cashier, 'Carl'))->get(route('entries.vouchers'))->assertForbidden();
});

it('speaks Arabic', function () {
    app()->setLocale('ar');
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $voucher = screenVoucher($ann);

    vouchersPage(screenUser(RoleId::FinancialManager, 'Fatima'))->assertSee('بانتظار الموافقة');
    vouchersPage(screenUser(RoleId::FinancialManager, 'Fatima'), ['voucher' => $voucher->id])->assertSee('اعتماد')->assertSee('السجل');
});

it('fills the dates from a month and clears them again', function () {
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $feb = screenVoucher(screenUser(RoleId::Accountant, 'Ann'), ['description' => 'February grant', 'date' => CarbonImmutable::parse('2026-02-10')]);
    screenVoucher(screenUser(RoleId::Accountant, 'Bob'), ['description' => 'March grant', 'date' => CarbonImmutable::parse('2026-03-10')]);

    $page = vouchersPage($manager)->set('month', '2026-02')
        ->assertSet('from', '2026-02-01')->assertSet('to', '2026-02-28')
        ->assertSee($feb->id)->assertDontSee('March grant');

    $page->set('month', '')->assertSet('from', '')->assertSet('to', '')->assertSee('March grant');
    $page->set('month', 'nonsense')->assertSet('month', '')->assertSet('from', '');
});

it('shows rejected vouchers only when asked and marks the amended ones', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $kept = screenVoucher($ann, ['description' => 'Kept grant']);
    $rejected = screenVoucher($ann, ['description' => 'Rejected grant']);
    $rejected->delete();

    vouchersPage($manager)->assertSee('Kept grant')->assertDontSee('Rejected grant')
        ->set('withRejected', true)->assertSee('Rejected grant')->assertSee(__('vouchers.status.rejected'))
        ->assertDontSeeHtml('data-test="amended-badge"');

    DB::table('gl_voucher_audit')->insert(['voucher_id' => $kept->id, 'action' => 'amend', 'field' => 'description', 'old_value' => 'a', 'new_value' => 'b', 'changed_by' => 1, 'reason' => 'typo']);

    vouchersPage($manager)->assertSeeHtml('data-test="amended-badge"');
});

it('counts the ticked vouchers and totals them next to the page total', function () {
    $ann = screenUser(RoleId::Accountant, 'Ann');
    $manager = screenUser(RoleId::FinancialManager, 'Fatima');
    $first = screenVoucher($ann, [], 100);
    $second = screenVoucher($ann, [], 250);
    $third = screenVoucher($ann, [], 40);

    $page = vouchersPage($manager)
        ->assertSee(__('common.selected_of', ['selected' => 0, 'total' => 3]))
        ->assertSee('390.00')->assertDontSeeHtml('data-test="selected-total"');

    $page->set('selected', [$first->id, $second->id])
        ->assertSee(__('common.selected_of', ['selected' => 2, 'total' => 3]))
        ->assertSeeHtml('data-test="selected-total"')->assertSee('350.00');

    $page->call('togglePage', [$first->id, $second->id, $third->id])->assertSet('selected', fn (array $ids) => count($ids) === 3);
    $page->call('togglePage', [$first->id, $second->id, $third->id])->assertSet('selected', []);
});
