<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Models\GlAccount;
use App\Models\MonthClose;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
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
    (new ChartOfAccountsSeeder)->run();
});

function approverUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** A pending receipt of $amount USD prepared by $by (the id of a legacy user). */
function pendingReceipt(int $by, int $amount = 100, string $date = '2026-03-10'): Voucher
{
    return app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $by, 'date' => CarbonImmutable::parse($date)], [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: 'F2F'),
    ]));
}

function approvals(): VoucherApprovals
{
    return app(VoucherApprovals::class);
}

it('lets the financial manager approve a pending voucher within the limit', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $manager = approverUser(RoleId::FinancialManager, 'fatima');
    $voucher = pendingReceipt(uid($accountant));

    $approved = approvals()->approve($voucher, $manager);

    expect($approved->status)->toBe(VoucherStatus::Approved)
        ->and($approved->approved_by)->toBe(uid($manager))
        ->and($approved->approved_at)->not->toBeNull()
        ->and(VoucherAudit::query()->where('voucher_id', $voucher->id)->orderBy('id')->pluck('action')->all())->toBe(['create', 'approve']);
});

it('refuses the person who prepared the voucher, even with head office rights', function () {
    $head = approverUser(RoleId::HeadOffice, 'hana');
    $voucher = pendingReceipt(uid($head));

    try {
        approvals()->approve($voucher, $head);
        $this->fail('Self-approval should have been refused.');
    } catch (LedgerRuleException $e) {
        expect($e->rules())->toBe([7]);
    }

    expect($voucher->fresh()->status)->toBe(VoucherStatus::PendingApproval)
        ->and(VoucherAudit::query()->where('action', 'approve')->count())->toBe(0);
});

it('refuses a role that cannot approve vouchers', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $other = approverUser(RoleId::Accountant, 'omar');
    $voucher = pendingReceipt(uid($accountant));

    expect(fn () => approvals()->approve($voucher, $other))->toThrow(LedgerRuleException::class)
        ->and($voucher->fresh()->status)->toBe(VoucherStatus::PendingApproval);
});

it('lets the financial manager approve a voucher of any amount', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $manager = approverUser(RoleId::FinancialManager, 'fatima');
    $big = pendingReceipt(uid($accountant), 1500);

    expect(approvals()->approve($big, $manager)->status)->toBe(VoucherStatus::Approved);
});

it('refuses to approve a voucher dated in a month that has since been closed', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $manager = approverUser(RoleId::FinancialManager, 'fatima');
    $voucher = pendingReceipt(uid($accountant));
    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);

    try {
        approvals()->approve($voucher, $manager);
        $this->fail('The closed month should have refused it.');
    } catch (LedgerRuleException $e) {
        expect($e->rules())->toBe([8]);
    }

    expect($voucher->fresh()->status)->toBe(VoucherStatus::PendingApproval);
});

it('checks the posting rules again, so an account turned off after posting stops the approval', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $manager = approverUser(RoleId::FinancialManager, 'fatima');
    $voucher = pendingReceipt(uid($accountant));
    GlAccount::query()->find('4101')->update(['is_active' => false]);

    try {
        approvals()->approve($voucher, $manager);
        $this->fail('The inactive account should have refused it.');
    } catch (LedgerRuleException $e) {
        expect($e->rules())->toBe([1]);
    }
});

it('only approves or rejects a voucher once', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $manager = approverUser(RoleId::FinancialManager, 'fatima');
    $voucher = pendingReceipt(uid($accountant));
    approvals()->approve($voucher, $manager);

    expect(fn () => approvals()->approve($voucher, $manager))->toThrow(LedgerRuleException::class)
        ->and(fn () => approvals()->reject($voucher, $manager, 'Changed my mind'))->toThrow(LedgerRuleException::class)
        ->and(VoucherAudit::query()->where('action', 'approve')->count())->toBe(1);
});

it('rejects a voucher with a reason: it keeps its number, is soft deleted and the reason is kept', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $manager = approverUser(RoleId::FinancialManager, 'fatima');
    $voucher = pendingReceipt(uid($accountant));

    approvals()->reject($voucher, $manager, '  Wrong project  ');

    $audit = VoucherAudit::query()->where('voucher_id', $voucher->id)->where('action', 'reject')->first();

    expect(Voucher::query()->find($voucher->id))->toBeNull()
        ->and(Voucher::onlyTrashed()->find($voucher->id)->status)->toBe(VoucherStatus::PendingApproval)
        ->and($audit->reason)->toBe('Wrong project')
        ->and($audit->changed_by)->toBe(uid($manager))
        ->and(pendingReceipt(uid($accountant))->id)->toBe('CRV-2026-0002');
});

it('refuses a rejection without a reason, by the preparer, or by a role that cannot approve', function () {
    $accountant = approverUser(RoleId::Accountant, 'ann');
    $manager = approverUser(RoleId::FinancialManager, 'fatima');
    $own = pendingReceipt(uid($manager));
    $voucher = pendingReceipt(uid($accountant));

    expect(fn () => approvals()->reject($voucher, $manager, '   '))->toThrow(LedgerRuleException::class)
        ->and(fn () => approvals()->reject($own, $manager, 'Mine'))->toThrow(LedgerRuleException::class)
        ->and(fn () => approvals()->reject($voucher, $accountant, 'Not mine to reject'))->toThrow(LedgerRuleException::class)
        ->and(Voucher::query()->count())->toBe(2);
});

function uid(User $user): int
{
    return (int) $user->getAuthIdentifier();
}
