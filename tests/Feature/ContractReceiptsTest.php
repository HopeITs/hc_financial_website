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
use App\Models\MonthClose;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Contracts\ContractActuals;
use App\Services\Contracts\ContractEntries;
use App\Services\Contracts\ContractException;
use App\Services\Contracts\ContractReceipts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
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
 * An EUR 10,000 draft agreement signed on 1 March at 1.15 $/EUR, over F2F (6,000) and SZ (4,000), paid in an
 * unconditional instalment of 6,000 and a conditional one of 4,000.
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

/** The agreement approved, with the income voucher of its first instalment prepared. */
function crApprovedAgreement(): Contract
{
    $contract = crAgreement();
    $contract->update(['approved_by' => 1, 'approved_at' => '2026-03-01 09:00:00', 'status' => 'running']);
    app(ContractEntries::class)->recogniseDue($contract, crUser(RoleId::Accountant, 'Setup'));

    return $contract;
}

/** @return list<array{0: string, 1: string|null, 2: string, 3: string, 4: string, 5: string, 6: string}> account, project, currency, debit, credit, debit USD, credit USD */
function crLines(string $voucherId): array
{
    return VoucherLine::query()->where('voucher_id', $voucherId)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line): array => [$line->account_code, $line->project_code, $line->currency, $line->debit_orig, $line->credit_orig, $line->debit_usd, $line->credit_usd])
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

it('prepares a pending income voucher for each unconditional instalment when the agreement is approved, and for a conditional one when its condition is met', function () {
    $contract = crAgreement();
    [$first, $second] = $contract->instalments()->get()->all();

    Livewire::actingAs(crUser(RoleId::FinancialManager, 'Fadi'))->test(ContractPage::class, ['contract' => $contract])
        ->call('approve')
        ->assertSee('Agreement approved.')
        ->assertSet('problems', []);

    $income = Voucher::query()->findOrFail($first->refresh()->recognition_voucher_id);

    // 6,000 EUR split 60/40 over the projects, at the fixed 1.15.
    expect($income->type)->toBe(VoucherType::Journal)
        ->and($income->status)->toBe(VoucherStatus::PendingApproval)
        ->and($income->date->toDateString())->toBe('2026-03-01')
        ->and(crLines($income->id))->toBe([
            ['1210', 'F2F', 'EUR', '3600.00', '0.00', '4140.00', '0.00'],
            ['4001', 'F2F', 'EUR', '0.00', '3600.00', '0.00', '4140.00'],
            ['1210', 'SZ', 'EUR', '2400.00', '0.00', '2760.00', '0.00'],
            ['4001', 'SZ', 'EUR', '0.00', '2400.00', '0.00', '2760.00'],
        ])
        ->and($second->refresh()->recognition_voucher_id)->toBeNull();

    Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertSeeHtml('data-test="income-1"')
        ->assertSee($income->id)
        ->call('markConditionMet', $second->id)
        ->assertSee('The condition of instalment 2 is met.');

    $conditional = $second->refresh()->recognition_voucher_id;

    expect(Voucher::query()->findOrFail($conditional)->date->toDateString())->toBe('2026-11-16')
        ->and(array_column(crLines($conditional), 3))->toBe(['2400.00', '0.00', '1600.00', '0.00']);
});

it('dates the income voucher today when the month it belongs to is closed, and lets it be prepared again when it could not be', function () {
    $contract = crAgreement();
    $contract->update(['approved_by' => 1, 'approved_at' => '2026-03-01 09:00:00', 'status' => 'running']);
    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);
    $first = $contract->instalments()->where('number', 1)->firstOrFail();

    $page = Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertSee('No income voucher')
        ->set('receiptInstalment', (string) $first->id)
        ->set('receiptAccount', '1161')
        ->set('receiptRate', '1.15')
        ->call('receiveInstalment')
        ->assertSee('Instalment 1 has no income voucher yet: prepare it first.')
        ->call('prepareRecognition', $first->id);

    $voucher = $first->refresh()->recognition_voucher_id;

    $page->assertSee("Income voucher {$voucher} of instalment 1 prepared and waiting for approval.")
        ->call('prepareRecognition', $first->id)
        ->assertSee("Instalment 1 already has its income voucher {$voucher}.");

    expect(Voucher::query()->findOrFail($voucher)->date->toDateString())->toBe('2026-11-16');
});

it('receives an instalment through the transfer company: commission to 5504, the exchange gain to 5503 for the organisation', function () {
    $contract = crApprovedAgreement();
    $first = $contract->instalments()->where('number', 1)->firstOrFail();

    $page = Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertSeeHtml('data-test="receipt-form"')
        ->set('receiptInstalment', (string) $first->id)
        ->assertSet('receiptAmount', '6000.00')
        ->assertSet('receiptSplit', ['F2F' => '3600.00', 'SZ' => '2400.00'])
        ->assertSet('receiptDate', '2026-11-16')
        ->set('receiptAccount', '1161')
        ->set('receiptDate', '2026-03-10')
        ->set('receiptRate', '1.18')
        ->set('receiptCommission', '20')
        ->call('receiveInstalment')
        ->assertHasNoErrors();

    $receipt = ContractReceipt::query()->sole();
    $voucher = Voucher::query()->findOrFail($receipt->voucher_id);
    $hawala = VoucherLine::query()->where('voucher_id', $voucher->id)->where('line_no', 1)->sole();

    // 6,000 EUR at 1.18 is 7,080 $; the company keeps 20 $; at the fixed 1.15 it is worth 6,900 $: a gain of 180 $.
    expect($voucher->type)->toBe(VoucherType::Receipt)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and(crLines($voucher->id))->toBe([
            ['1161', null, 'USD', '7060.00', '0.00', '7060.00', '0.00'],
            ['5504', null, 'USD', '20.00', '0.00', '20.00', '0.00'],
            ['1210', 'F2F', 'EUR', '0.00', '3600.00', '0.00', '4140.00'],
            ['1210', 'SZ', 'EUR', '0.00', '2400.00', '0.00', '2760.00'],
            ['5503', null, 'USD', '0.00', '180.00', '0.00', '180.00'],
        ])
        ->and([$hawala->eur_amount, $hawala->eur_rate, $hawala->commission_usd, $hawala->contact])->toBe(['6000.00', '1.180000', '20.00', 'Transfer company'])
        ->and($receipt->amount_usd)->toBe('7080.00');

    $page->assertSee("Receipt voucher {$voucher->id} prepared and waiting for approval.")
        ->assertSee('Waiting for approval')
        ->assertSeeHtml('data-test="nothing-to-receive"');

    expect(app(ContractReceipts::class)->status($contract)[$first->id])->toMatchArray(['received' => '0.00', 'pending' => '6000.00', 'remaining' => '0.00', 'state' => 'none'])
        ->and(app(ContractActuals::class)->receivedByProject($contract))->toBe([])
        ->and(ContractAudit::query()->where('action', 'receipt_linked')->count())->toBe(1);

    $voucher->update(['status' => VoucherStatus::Approved]);

    expect(app(ContractReceipts::class)->status($contract)[$first->id])->toMatchArray([
        'received' => '6000.00', 'received_usd' => '7080.00', 'expected_usd' => '6900.00', 'difference_usd' => '180.00', 'pending' => '0.00', 'state' => 'full',
    ])
        ->and(app(ContractReceipts::class)->status($contract)[$first->id]['links'][0])->toMatchArray(['voucher' => $voucher->id, 'date' => '2026-03-10', 'approved' => true])
        ->and(app(ContractActuals::class)->receivedByProject($contract))->toBe(['F2F' => '3600.00', 'SZ' => '2400.00']);
});

it('receives part of an instalment into a euro bank at the actual rate, with a loss and a split changed by hand', function () {
    $contract = crApprovedAgreement();
    $first = $contract->instalments()->where('number', 1)->firstOrFail();

    Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->set('receiptInstalment', (string) $first->id)
        ->set('receiptAmount', '2000')
        ->assertSet('receiptSplit', ['F2F' => '1200.00', 'SZ' => '800.00'])
        ->set('receiptSplit.F2F', '2000')
        ->set('receiptSplit.SZ', '0')
        ->set('receiptAccount', '1176')
        ->set('receiptRate', '1.10')
        ->call('receiveInstalment')
        ->assertHasNoErrors();

    // 2,000 EUR at 1.10 is 2,200 $, worth 2,300 $ at the fixed rate: a loss of 100 $.
    expect(crLines(ContractReceipt::query()->sole()->voucher_id))->toBe([
        ['1176', null, 'EUR', '2000.00', '0.00', '2200.00', '0.00'],
        ['1210', 'F2F', 'EUR', '0.00', '2000.00', '0.00', '2300.00'],
        ['5503', null, 'USD', '100.00', '0.00', '100.00', '0.00'],
    ])
        ->and(app(ContractEntries::class)->remaining($first))->toBe('4000.00');
});

it('refuses a receipt that does not fit the instalment, the account, the commission or the split', function () {
    $contract = crApprovedAgreement();
    [$first, $second] = $contract->instalments()->get()->all();
    $user = crUser(RoleId::Accountant, 'Ann');
    $receive = fn (ContractInstalment $instalment, string $account, string $amount, string $commission, array $split) => crRefusal(
        fn () => app(ContractEntries::class)->receive($contract, $instalment, $account, CarbonImmutable::parse('2026-03-10'), $amount, '1.15', $commission, $split, $user),
    );

    expect($receive($second, '1161', '4000.00', '0', ['F2F' => '2400.00', 'SZ' => '1600.00']))->toBe(['Instalment 2 is conditional: mark its condition as met first.'])
        ->and($receive($first, '1111', '7000.00', '0', ['F2F' => '7000.00']))->toBe([
            'Choose the transfer company or a bank account in the agreement currency or in dollars.',
            'Instalment 1 needs only 6000.00 more.',
        ])
        ->and($receive($first, '1176', '1000.00', '5', ['F2F' => '500.00', 'CORE' => '500.00']))->toBe([
            'The commission is in dollars, only into a dollar account, and must be less than the amount received.',
            'The split can name only this agreement\'s projects, with no negative part.',
        ])
        ->and($receive($first, '1161', '1000.00', '0', ['F2F' => '500.00', 'SZ' => '400.00']))->toBe(['The parts add up to 900.00, not to the amount received (1000.00).'])
        ->and(ContractReceipt::query()->count())->toBe(0)
        ->and(Voucher::query()->where('type', VoucherType::Receipt->value)->count())->toBe(0);
});

it('lets only the preparing roles receive an instalment', function () {
    $contract = crApprovedAgreement();

    Livewire::actingAs(crUser(RoleId::GeneralManager, 'Gina'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertDontSeeHtml('data-test="receipt-form"')
        ->set('receiptInstalment', (string) $contract->instalments()->value('id'))
        ->set('receiptAccount', '1161')
        ->set('receiptAmount', '6000')
        ->set('receiptRate', '1.15')
        ->call('receiveInstalment')
        ->assertForbidden();

    expect(ContractReceipt::query()->count())->toBe(0);
});
