<?php

use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\ContractPage;
use App\Models\Contract;
use App\Models\ContractAudit;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\ContractReceipt;
use App\Models\Donor;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Contracts\ContractActuals;
use App\Services\Contracts\ContractEntries;
use App\Services\Contracts\ContractException;
use App\Services\Contracts\ContractReceipts;
use App\Services\Treasury\DailyRates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    (require database_path('migrations/2026_11_25_090000_create_gl_recurring_payments_table.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-16 10:00:00');
});

function crUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/**
 * An EUR 10,000 draft agreement at 1.15 $/EUR, over F2F (6,000) and SZ (4,000), paid in an unconditional instalment of
 * 6,000 and a conditional one of 4,000.
 */
function crAgreement(): Contract
{
    $donor = Donor::query()->create([
        'code' => 'CARPL', 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس بولندا', 'type' => 'church_agency',
        'country' => 'Poland', 'currency' => 'EUR', 'is_active' => true,
    ]);
    $contract = Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => $donor->id, 'title' => 'Education 2026', 'signed_on' => '2026-03-01', 'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28',
        'currency' => 'EUR', 'amount' => '10000', 'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'draft', 'created_by' => 1,
    ]);

    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'F2F', 'amount' => '6000']);
    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'SZ', 'amount' => '4000']);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 1, 'due_on' => '2026-03-01', 'amount' => '6000', 'is_conditional' => false]);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 2, 'due_on' => '2026-09-01', 'amount' => '4000', 'condition' => 'After the interim report', 'is_conditional' => true]);

    return $contract;
}

function crApprovedAgreement(): Contract
{
    $contract = crAgreement();
    $contract->update(['approved_by' => 1, 'approved_at' => '2026-03-01 09:00:00', 'status' => 'running']);

    return $contract;
}

/** @return list<array{0: string, 1: string|null, 2: string|null, 3: string, 4: string, 5: string, 6: string, 7: string}> account, project, budget line, currency, debit, credit, debit USD, credit USD */
function crLines(string $voucherId): array
{
    return VoucherLine::query()->where('voucher_id', $voucherId)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line): array => [$line->account_code, $line->project_code, $line->budget_line_key, $line->currency, $line->debit_orig, $line->credit_orig, $line->debit_usd, $line->credit_usd])
        ->all();
}

/** @return list<string> */
function crRefusal(callable $action): array
{
    try {
        $action();
    } catch (ContractException $refused) {
        return $refused->problems;
    }

    return [];
}

it('makes no voucher when the agreement is approved or a condition is met', function () {
    $contract = crAgreement();
    $second = $contract->instalments()->where('number', 2)->firstOrFail();

    Livewire::actingAs(crUser(RoleId::FinancialManager, 'Fadi'))->test(ContractPage::class, ['contract' => $contract])
        ->call('approve')
        ->assertSee('Agreement approved.')
        ->assertSet('problems', []);

    Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->call('markConditionMet', $second->id)
        ->assertSee('The condition of instalment 2 is met.');

    expect(Voucher::query()->count())->toBe(0);
});

it('receives euros into the euro bank at the day\'s rate, the donor\'s grant credited per project, the bank\'s fee charged to the organisation', function () {
    $contract = crApprovedAgreement();
    $first = $contract->instalments()->where('number', 1)->firstOrFail();
    app(DailyRates::class)->propose('EUR', '2026-11-16', '1.16', null, crUser(RoleId::FinancialManager, 'Fadi'), approveNow: true);

    $page = Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertSeeHtml('data-test="receipt-form"')
        ->set('receiptInstalment', (string) $first->id)
        ->assertSet('receiptAmount', '6000.00')
        ->assertSet('receiptSplit', ['F2F' => '3600.00', 'SZ' => '2400.00'])
        ->set('receiptAccount', '1176')
        ->assertSet('receiptRate', '1.16')
        ->assertSeeHtml('data-test="receipt-daily-rate"')
        ->assertDontSeeHtml('data-test="receipt-rate"')
        ->set('receiptFee', '15')
        ->call('receiveInstalment')
        ->assertHasNoErrors()
        ->assertSet('problems', []);

    $receipt = ContractReceipt::query()->sole();
    $voucher = Voucher::query()->findOrFail($receipt->voucher_id);
    $grant = VoucherLine::query()->where('voucher_id', $voucher->id)->where('account_code', '4001')->where('project_code', 'F2F')->sole();

    // 6,000 EUR at 1.16 is 6,960 $; the bank keeps 15 EUR (17.40 $); the euros stay euros in the bank.
    expect($voucher->type)->toBe(VoucherType::Receipt)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and(crLines($voucher->id))->toBe([
            ['1176', null, null, 'EUR', '5985.00', '0.00', '6942.60', '0.00'],
            ['5501', null, null, 'EUR', '15.00', '0.00', '17.40', '0.00'],
            ['4001', 'F2F', null, 'EUR', '0.00', '3600.00', '0.00', '4176.00'],
            ['4001', 'SZ', null, 'EUR', '0.00', '2400.00', '0.00', '2784.00'],
        ])
        ->and([$grant->donor, $grant->donor_currency, $grant->credit_donor])->toBe(['Caritas Poland', 'EUR', '3600.00'])
        ->and($receipt->amount_usd)->toBe('6960.00');

    $page->assertSee("Receipt voucher {$voucher->id} prepared and waiting for approval.")
        ->assertSee('Waiting for approval');

    expect(app(ContractReceipts::class)->status($contract)[$first->id])->toMatchArray(['received' => '0.00', 'pending' => '6000.00', 'remaining' => '0.00', 'state' => 'none'])
        ->and(app(ContractActuals::class)->receivedByProject($contract))->toBe([])
        ->and(ContractAudit::query()->where('action', 'receipt_linked')->count())->toBe(1);

    $voucher->update(['status' => VoucherStatus::Approved]);

    expect(app(ContractReceipts::class)->status($contract)[$first->id])->toMatchArray(['received' => '6000.00', 'received_usd' => '6960.00', 'state' => 'full'])
        ->and(app(ContractActuals::class)->receivedByProject($contract))->toBe(['F2F' => '3600.00', 'SZ' => '2400.00']);
});

it('receives straight into the transfer company at the rate obtained, its fee charged to a project\'s budget line', function () {
    $contract = crApprovedAgreement();
    $second = $contract->instalments()->where('number', 2)->firstOrFail();

    Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->set('receiptInstalment', (string) $second->id)
        ->set('receiptAccount', '1161')
        ->assertSet('receiptRate', '')
        ->set('receiptRate', '1.18')
        ->set('receiptFee', '20')
        ->set('receiptFeeTo', 'F2F|Bank charges')
        ->call('receiveInstalment')
        ->assertSet('problems', []);

    // 4,000 EUR converted at 1.18 is 4,720 $; the company keeps 20 $, charged to F2F's bank charges.
    expect(crLines(ContractReceipt::query()->sole()->voucher_id))->toBe([
        ['1161', null, null, 'USD', '4700.00', '0.00', '4700.00', '0.00'],
        ['5501', 'F2F', 'F2F|Bank charges', 'USD', '20.00', '0.00', '20.00', '0.00'],
        ['4001', 'F2F', null, 'EUR', '0.00', '2400.00', '0.00', '2832.00'],
        ['4001', 'SZ', null, 'EUR', '0.00', '1600.00', '0.00', '1888.00'],
    ]);
});

it('refuses a receipt into another currency\'s bank, beyond the instalment, with a bad fee or a split that does not add up', function () {
    $contract = crApprovedAgreement();
    $first = $contract->instalments()->where('number', 1)->firstOrFail();
    $user = crUser(RoleId::Accountant, 'Ann');
    app(DailyRates::class)->propose('EUR', '2026-11-16', '1.16', null, crUser(RoleId::FinancialManager, 'Fadi'), approveNow: true);
    $receive = fn (string $account, string $amount, string $fee, string $feeTo, array $split) => crRefusal(
        fn () => app(ContractEntries::class)->receive($contract, $first, $account, CarbonImmutable::parse('2026-11-16'), $amount, '1.16', $fee, $feeTo, $split, $user),
    );

    expect($receive('1171', '7000.00', '0', 'organisation', ['F2F' => '7000.00']))->toBe([
        'Choose a EUR bank account or the transfer company.',
        'Instalment 1 needs only 6000.00 more.',
    ])
        ->and($receive('1176', '1000.00', '1000', 'organisation', ['F2F' => '1000.00']))->toBe(['The fee cannot be negative and must be less than the amount.'])
        ->and($receive('1176', '1000.00', '5', 'NOPE|Nothing', ['F2F' => '500.00', 'SZ' => '400.00']))->toBe([
            'Charge the fee to the organisation or to a budget line of this agreement\'s projects.',
            'The parts add up to 900.00, not to the amount received (1000.00).',
        ])
        ->and(Voucher::query()->count())->toBe(0);
});

it('lets only the preparing roles receive an instalment', function () {
    $contract = crApprovedAgreement();

    Livewire::actingAs(crUser(RoleId::GeneralManager, 'Gina'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertDontSeeHtml('data-test="receipt-form"')
        ->set('receiptInstalment', (string) $contract->instalments()->value('id'))
        ->set('receiptAccount', '1176')
        ->set('receiptAmount', '6000')
        ->set('receiptRate', '1.16')
        ->call('receiveInstalment')
        ->assertForbidden();

    expect(ContractReceipt::query()->count())->toBe(0);
});

it('takes the day\'s approved rate by itself for euros kept in the bank, and waits for one when there is none', function () {
    $contract = crApprovedAgreement();
    $first = $contract->instalments()->where('number', 1)->firstOrFail();
    $user = crUser(RoleId::Accountant, 'Ann');
    $receive = fn () => app(ContractEntries::class)->receive($contract, $first, '1176', CarbonImmutable::parse('2026-11-16'), '1000.00', '9.99', '0', 'organisation', ['F2F' => '1000.00'], $user);

    expect(crRefusal($receive))->toBe([__('treasury.no_rate', ['currency' => 'EUR', 'date' => '16/11/2026'])])
        ->and(Voucher::query()->count())->toBe(0);

    app(DailyRates::class)->propose('EUR', '2026-11-16', '1.16', null, crUser(RoleId::FinancialManager, 'Fadi'), approveNow: true);

    // The 9.99 typed is ignored: 1,000 EUR at the day's 1.16 is 1,160 $, an estimate until they are withdrawn.
    expect(crLines($receive()->id)[0])->toBe(['1176', null, null, 'EUR', '1000.00', '0.00', '1160.00', '0.00']);
});
