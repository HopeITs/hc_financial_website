<?php

use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Approvals\ApprovalChain;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\VoucherApprovals;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    createFinanceSchema();

    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    DB::connection('legacy')->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function arUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/**
 * A pending voucher with its lines written straight to the tables: [account, side, usd] per line.
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $lines
 */
function arVoucher(VoucherType $type, array $lines, int $preparedBy): Voucher
{
    $voucher = Voucher::query()->create([
        'id' => $type->value.'-2026-'.str_pad((string) (Voucher::query()->count() + 1), 4, '0', STR_PAD_LEFT),
        'type' => $type, 'date' => '2026-03-10', 'status' => VoucherStatus::PendingApproval, 'prepared_by' => $preparedBy,
    ]);

    foreach ($lines as $index => [$account, $side, $usd]) {
        VoucherLine::query()->create([
            'voucher_id' => $voucher->id, 'line_no' => $index + 1, 'account_code' => $account, 'currency' => 'USD',
            $side === 'debit' ? 'debit_orig' : 'credit_orig' => $usd, $side === 'debit' ? 'debit_usd' : 'credit_usd' => $usd,
            'project_code' => 'F2F',
        ] + ($account === '1161' ? ['fund_source' => 'treasury', 'contact' => 'Transfer company'] : []));
    }

    return $voucher;
}

/** A project transfer in two legs: the cash leg and the transfer company leg each post a debit. */
function arTransfer(string $cash, string $company, int $preparedBy): Voucher
{
    return arVoucher(VoucherType::Transfer, [
        ['1111', 'debit', $cash], ['1111', 'credit', $cash], ['1161', 'credit', $company], ['1161', 'debit', $company],
    ], $preparedBy);
}

/** A donor receipt through the transfer company (account 1161). */
function arDonorReceipt(string $amount, int $preparedBy): Voucher
{
    return arVoucher(VoucherType::Receipt, [['1161', 'debit', $amount], ['4101', 'credit', $amount]], $preparedBy);
}

// R4-1

it('measures a transfer by its larger leg once, not by every debit line', function () {
    expect(arTransfer('5000.00', '5067.00', 999)->approvalAmount())->toBe('5067.00')
        ->and(arVoucher(VoucherType::Transfer, [['1111', 'debit', '5000.00'], ['1111', 'credit', '5000.00']], 999)->approvalAmount())->toBe('5000.00');
});

it('lets the Financial Manager approve a 5,000 USD transfer whose debit lines add up to 10,000', function () {
    $manager = arUser(RoleId::FinancialManager, 'Aram');
    $transfer = arTransfer('5000.00', '5000.00', 999);

    expect((float) $transfer->lines()->sum('debit_usd'))->toBe(10000.0)
        ->and(Gate::forUser($manager)->allows('approve', $transfer))->toBeTrue();
});

it('keeps the sum of debits for the other voucher types', function () {
    $payment = arVoucher(VoucherType::Payment, [['1121', 'debit', '300.00'], ['1121', 'debit', '200.00'], ['1111', 'credit', '500.00']], 999);

    expect($payment->approvalAmount())->toBe('500.00');
});

// R4-2

it('recognises a donor receipt by the transfer company debit on a receipt', function () {
    $receipt = arDonorReceipt('3000.00', 999);
    $plain = arVoucher(VoucherType::Receipt, [['1111', 'debit', '3000.00'], ['4101', 'credit', '3000.00']], 999);
    $payment = arVoucher(VoucherType::Payment, [['1161', 'debit', '300.00'], ['1111', 'credit', '300.00']], 999);

    expect($receipt->isDonorReceipt())->toBeTrue()
        ->and($plain->isDonorReceipt())->toBeFalse()
        ->and($payment->isDonorReceipt())->toBeFalse();
});

it('lets the Financial Manager approve a donor receipt of any amount', function () {
    $manager = arUser(RoleId::FinancialManager, 'Aram');

    expect(Gate::forUser($manager)->allows('approve', arDonorReceipt('50000.00', 999)))->toBeTrue();
});

it('approves a donor receipt up to USD 2,000 with the Financial Manager alone', function () {
    $manager = arUser(RoleId::FinancialManager, 'Aram');
    $approved = app(VoucherApprovals::class)->approve(arDonorReceipt('2000.00', 999), $manager);

    expect($approved->status)->toBe(VoucherStatus::Approved)
        ->and($approved->approved_by)->toBe((int) $manager->getKey());
});

it('needs the General Manager after the Financial Manager above USD 2,000', function () {
    $manager = arUser(RoleId::FinancialManager, 'Aram');
    $general = arUser(RoleId::GeneralManager, 'Gina');
    $approvals = app(VoucherApprovals::class);
    $receipt = arDonorReceipt('2000.01', 999);

    expect(Gate::forUser($general)->allows('approve', $receipt))->toBeFalse()
        ->and(fn () => $approvals->approve($receipt, $general))->toThrow(LedgerRuleException::class);

    $afterFirst = $approvals->approve($receipt, $manager);

    expect($afterFirst->status)->toBe(VoucherStatus::PendingApproval)
        ->and($afterFirst->approved_by)->toBeNull()
        ->and($afterFirst->approvedSteps())->toBe([RoleId::FinancialManager])
        ->and(Gate::forUser($manager)->allows('approve', $afterFirst))->toBeFalse()
        ->and(Gate::forUser($general)->allows('approve', $afterFirst))->toBeTrue();

    $done = $approvals->approve($afterFirst, $general);

    expect($done->status)->toBe(VoucherStatus::Approved)
        ->and($done->approved_by)->toBe((int) $general->getKey());
});

it('does not let the General Manager approve or reject an ordinary voucher', function () {
    $general = arUser(RoleId::GeneralManager, 'Gina');
    $voucher = arVoucher(VoucherType::Receipt, [['1111', 'debit', '3000.00'], ['4101', 'credit', '3000.00']], 999);

    expect(Gate::forUser($general)->allows('approve', $voucher))->toBeFalse()
        ->and(fn () => app(VoucherApprovals::class)->reject($voucher, $general, 'No'))->toThrow(LedgerRuleException::class);
});

it('puts the Program Manager before the Financial Manager only when asked for', function () {
    $chain = new ApprovalChain;

    expect($chain->stepsFor('2000.00'))->toBe([RoleId::FinancialManager])
        ->and($chain->stepsFor('2000.01'))->toBe([RoleId::FinancialManager, RoleId::GeneralManager])
        ->and($chain->stepsFor('2000.01', true))->toBe([RoleId::ProgramManager, RoleId::FinancialManager, RoleId::GeneralManager])
        ->and($chain->mayApproveNext('100.00', [], [RoleId::FinancialManager], true))->toBeFalse()
        ->and($chain->mayApproveNext('100.00', [], [RoleId::ProgramManager], true))->toBeTrue()
        ->and($chain->mayApproveNext('100.00', [RoleId::ProgramManager], [RoleId::HeadOffice], true))->toBeTrue();
});
