<?php

use App\Enums\RoleId;
use App\Livewire\ContractPage;
use App\Models\Contract;
use App\Models\ContractAudit;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\ContractReceipt;
use App\Models\Donor;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Contracts\ContractReceipts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    Carbon::setTestNow('2026-11-16 10:00:00');

    foreach ([['1111', 'assets', 'Cash Aleppo', true], ['4101', 'income', 'Grants', false]] as [$code, $class, $name, $cash]) {
        GlAccount::query()->create([
            'code' => $code, 'parent_code' => null, 'level' => 'posting', 'class' => $class, 'name_en' => $name, 'name_ar' => $name,
            'normal_side' => $class === 'income' ? 'credit' : 'debit', 'currency' => 'Any', 'is_cash' => $cash, 'required_tags' => [], 'is_active' => true,
        ]);
    }

    foreach (['EDU', 'SZ', 'OUT'] as $code) {
        GlProject::query()->create(['code' => $code, 'name' => $code, 'restriction' => 'With donor restrictions', 'donor_currency' => 'EUR', 'donor_rate' => 1.15, 'is_general_account' => false]);
    }
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

/** An EUR 10,000 agreement at 1.15 $/EUR over EDU (6,000) and SZ (4,000), paid in two instalments of 6,000 and 4,000. */
function crAgreement(): Contract
{
    $donor = Donor::query()->create([
        'code' => 'CARPL', 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس بولندا', 'type' => 'church_agency',
        'country' => 'Poland', 'currency' => 'EUR', 'is_active' => true,
    ]);
    $contract = Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => $donor->id, 'title' => 'Education 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28',
        'currency' => 'EUR', 'amount' => '10000', 'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'running', 'created_by' => 1,
        'approved_by' => 1, 'approved_at' => '2026-03-01 09:00:00',
    ]);

    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'EDU', 'amount' => '6000']);
    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'SZ', 'amount' => '4000']);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 1, 'due_on' => '2026-03-01', 'amount' => '6000', 'is_conditional' => false]);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 2, 'due_on' => '2026-06-01', 'amount' => '4000', 'is_conditional' => false]);

    return $contract;
}

/**
 * A receipt voucher: the cash account debited with the total, and one income line per project.
 *
 * @param  array<string, array{0: float, 1: float}>  $income  project => [EUR, USD]
 */
function crReceipt(string $id, string $date, string $status, array $income): void
{
    DB::table('gl_vouchers')->insert(['id' => $id, 'type' => 'CRV', 'date' => $date, 'status' => $status, 'prepared_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $line = fn (int $number, array $values) => DB::table('gl_voucher_lines')->insert($values + [
        'voucher_id' => $id, 'line_no' => $number, 'currency' => 'EUR', 'rate' => 1, 'donor_currency' => 'EUR',
        'debit_orig' => 0, 'credit_orig' => 0, 'debit_usd' => 0, 'credit_usd' => 0, 'debit_donor' => 0, 'credit_donor' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $line(1, ['account_code' => '1111', 'debit_orig' => array_sum(array_column($income, 0)), 'debit_usd' => array_sum(array_column($income, 1))]);
    $number = 2;
    foreach ($income as $project => [$eur, $usd]) {
        $line($number++, ['account_code' => '4101', 'project_code' => $project, 'credit_orig' => $eur, 'credit_usd' => $usd, 'credit_donor' => $eur]);
    }
}

it('lists the approved receipts on the agreement\'s projects that can still be linked', function () {
    $contract = crAgreement();
    crReceipt('CRV-2026-0001', '2026-03-10', 'approved', ['EDU' => [6000, 7200]]);
    crReceipt('CRV-2026-0002', '2026-06-05', 'approved', ['EDU' => [1000, 1100], 'SZ' => [3000, 3300]]);
    crReceipt('CRV-2026-0003', '2026-07-01', 'pending_approval', ['EDU' => [500, 575]]);
    crReceipt('CRV-2026-0004', '2026-07-02', 'approved', ['OUT' => [700, 805]]);

    $candidates = app(ContractReceipts::class)->candidates($contract);

    expect(array_keys($candidates))->toEqualCanonicalizing(['CRV-2026-0001', 'CRV-2026-0002'])
        ->and($candidates['CRV-2026-0002'])->toMatchArray(['date' => '2026-06-05', 'amount' => '4000.00', 'usd' => '4400.00', 'available' => '4000.00', 'account' => '1111 · Cash Aleppo']);
});

it('links receipts to instalments, shows what arrived with the exchange difference, and logs it', function () {
    $contract = crAgreement();
    crReceipt('CRV-2026-0001', '2026-03-10', 'approved', ['EDU' => [6000, 7200]]);
    crReceipt('CRV-2026-0002', '2026-06-05', 'approved', ['EDU' => [1000, 1100], 'SZ' => [3000, 3300]]);
    [$first, $second] = $contract->instalments()->get()->all();
    $accountant = crUser(RoleId::Accountant, 'Ann');

    $page = Livewire::actingAs($accountant)->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertSeeHtml('data-test="receipt-form"')
        ->set('receiptInstalment', (string) $first->id)
        ->set('receiptVoucher', 'CRV-2026-0001')
        ->assertSet('receiptAmount', '6000.00')
        ->call('linkReceipt')
        ->assertHasNoErrors()
        ->assertSee('Receipt linked to the instalment.')
        ->set('receiptInstalment', (string) $second->id)
        ->set('receiptVoucher', 'CRV-2026-0002')
        ->set('receiptAmount', '1500')
        ->call('linkReceipt')
        ->assertHasNoErrors();

    $status = app(ContractReceipts::class)->status($contract);

    // 6,000 EUR booked at 7,200 $ against 6,900 $ at the fixed 1.15: a gain of 300 $.
    expect($status[$first->id])->toMatchArray(['received' => '6000.00', 'received_usd' => '7200.00', 'expected_usd' => '6900.00', 'difference_usd' => '300.00', 'remaining' => '0.00', 'state' => 'full'])
        ->and($status[$first->id]['links'][0])->toMatchArray(['voucher' => 'CRV-2026-0001', 'date' => '2026-03-10', 'account' => '1111 · Cash Aleppo'])
        // 1,500 of a 4,000 EUR voucher booked at 4,400 $ carries 1,650 $; at 1.15 it is worth 1,725 $: a loss of 75 $.
        ->and($status[$second->id])->toMatchArray(['received' => '1500.00', 'received_usd' => '1650.00', 'difference_usd' => '-75.00', 'remaining' => '2500.00', 'state' => 'part'])
        ->and(app(ContractReceipts::class)->candidates($contract))->toHaveKey('CRV-2026-0002')
        ->and(app(ContractReceipts::class)->candidates($contract)['CRV-2026-0002']['available'])->toBe('2500.00')
        ->and(app(ContractReceipts::class)->candidates($contract))->not->toHaveKey('CRV-2026-0001');

    $page->assertSeeHtml('data-test="received-1"')->assertSee('Partly received');

    expect(ContractAudit::query()->where('action', 'receipt_linked')->count())->toBe(2);

    $page->call('unlinkReceipt', ContractReceipt::query()->where('voucher_id', 'CRV-2026-0002')->value('id'))
        ->assertSee('Receipt unlinked.');

    expect(app(ContractReceipts::class)->status($contract)[$second->id]['state'])->toBe('none')
        ->and(ContractAudit::query()->where('action', 'receipt_unlinked')->sole()->details)->toMatchArray(['instalment' => 2, 'voucher' => 'CRV-2026-0002', 'amount' => '1500.00']);
});

it('refuses to link more than the voucher has left or the instalment still needs, or the same voucher twice', function () {
    $contract = crAgreement();
    crReceipt('CRV-2026-0002', '2026-06-05', 'approved', ['EDU' => [1000, 1100], 'SZ' => [3000, 3300]]);
    $second = $contract->instalments()->where('number', 2)->firstOrFail();

    Livewire::actingAs(crUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->set('receiptInstalment', (string) $second->id)
        ->set('receiptVoucher', 'CRV-2026-0002')
        ->set('receiptAmount', '5000')
        ->call('linkReceipt')
        ->assertSee('Voucher CRV-2026-0002 has only 4000.00 left to link.')
        ->assertSee('Instalment 2 needs only 4000.00 more.')
        ->set('receiptAmount', '1000')
        ->call('linkReceipt')
        ->set('receiptInstalment', (string) $second->id)
        ->set('receiptVoucher', 'CRV-2026-0002')
        ->set('receiptAmount', '1000')
        ->call('linkReceipt')
        ->assertSee('Voucher CRV-2026-0002 is already linked to instalment 2.')
        ->set('receiptVoucher', 'CRV-2026-0009')
        ->call('linkReceipt')
        ->assertSee('Voucher CRV-2026-0009 has no income to this agreement\'s projects left to link.');

    expect(ContractReceipt::query()->sum('amount'))->toEqual(1000);
});

it('lets only the preparing roles link a receipt', function () {
    $contract = crAgreement();
    crReceipt('CRV-2026-0001', '2026-03-10', 'approved', ['EDU' => [6000, 7200]]);

    Livewire::actingAs(crUser(RoleId::GeneralManager, 'Gina'))->test(ContractPage::class, ['contract' => $contract])
        ->set('tab', 'instalments')
        ->assertDontSeeHtml('data-test="receipt-form"')
        ->set('receiptInstalment', (string) $contract->instalments()->value('id'))
        ->set('receiptVoucher', 'CRV-2026-0001')
        ->set('receiptAmount', '6000')
        ->call('linkReceipt')
        ->assertForbidden();

    expect(ContractReceipt::query()->count())->toBe(0);
});
