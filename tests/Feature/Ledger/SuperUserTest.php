<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\ScreenAction;
use App\Enums\VoucherStatus;
use App\Models\GlProject;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\WebUserRole;
use App\Services\Access\LegacyDataScope;
use App\Services\Approvals\ApprovalChain;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherApprovals;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();

    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    DB::connection('legacy')->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_11_01_090000_add_is_super_to_audit_tables.php'))->up();
    (new ChartOfAccountsSeeder)->run();
    GlProject::query()->create(['code' => 'F2F', 'name' => 'F2F', 'restriction' => 'restricted']);
});

/** A cashier who also holds the Super User role. */
function superUser(): User
{
    $id = createFinanceUser('sam', 'secret pass', RoleId::Cashier->name, RoleId::Cashier->value);
    WebUserRole::query()->create(['user_id' => $id, 'role_id' => RoleId::SuperUser->value]);

    return User::query()->findOrFail($id);
}

function superReceipt(int $by, int $amount = 100): Voucher
{
    return app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $by, 'date' => CarbonImmutable::parse('2026-03-10')], [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: 'F2F'),
    ]));
}

it('does nothing while the flag is off', function () {
    config(['finance.super_user' => false]);
    $super = superUser();

    expect($super->isSuperUser())->toBeFalse()
        ->and($super->roleIds())->toBe([RoleId::Cashier])
        ->and($super->canOnScreen('users', ScreenAction::Delete))->toBeFalse();
});

it('does nothing in production even with the flag on', function () {
    config(['finance.super_user' => true]);
    app()->detectEnvironment(fn () => 'production');
    $super = superUser();

    expect($super->isSuperUser())->toBeFalse()
        ->and($super->canOnScreen('users', ScreenAction::Delete))->toBeFalse();
});

it('opens every screen, sees all data and takes every approval step when on', function () {
    config(['finance.super_user' => true]);
    $super = superUser();

    expect($super->isSuperUser())->toBeTrue()
        ->and($super->canOnScreen('users', ScreenAction::Delete))->toBeTrue()
        ->and(app(LegacyDataScope::class)->for($super)->unrestricted)->toBeTrue()
        ->and(app(ApprovalChain::class)->mayApproveNext('5000.00', [RoleId::FinancialManager], $super->roleIds()))->toBeTrue();
});

it('approves its own voucher and marks the audit row, while an ordinary approver is still refused', function () {
    config(['finance.super_user' => true]);
    $super = superUser();
    $voucher = superReceipt(uid($super));
    $this->actingAs($super);

    $approved = app(VoucherApprovals::class)->approve($voucher, $super);

    expect($approved->status)->toBe(VoucherStatus::Approved)
        ->and(VoucherAudit::query()->where('voucher_id', $voucher->id)->where('action', 'approve')->value('is_super'))->toBeTruthy();

    $head = User::query()->findOrFail(createFinanceUser('hana', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
    $own = superReceipt(uid($head));

    expect(fn () => app(VoucherApprovals::class)->approve($own, $head))->toThrow(LedgerRuleException::class);
});

it('shows the warning band only to the Super User', function () {
    config(['finance.super_user' => true]);

    $this->actingAs(superUser())->get('/home')->assertSee('data-test="super-user-banner"', false);

    $plain = User::query()->findOrFail(createFinanceUser('pat', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));
    $this->actingAs($plain)->get('/home')->assertDontSee('data-test="super-user-banner"', false);
});
