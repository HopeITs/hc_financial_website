<?php

use App\Enums\RoleId;
use App\Livewire\ContractPage;
use App\Livewire\Contracts;
use App\Models\Contract;
use App\Models\ContractAudit;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\Donor;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\WebUserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    Storage::fake('local');

    foreach ([['5101', 'expenses'], ['1111', 'assets']] as [$code, $class]) {
        GlAccount::query()->create([
            'code' => $code, 'parent_code' => null, 'level' => 'posting', 'class' => $class, 'name_en' => $code, 'name_ar' => $code,
            'normal_side' => 'debit', 'currency' => 'USD', 'is_cash' => $class === 'assets', 'required_tags' => [], 'is_active' => true,
        ]);
    }

    foreach (['EDU', 'SHARED'] as $code) {
        GlProject::query()->create(['code' => $code, 'name' => $code, 'restriction' => 'With donor restrictions', 'donor_currency' => 'EUR', 'donor_rate' => 1.15, 'is_general_account' => false]);
    }
});

function cdUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/**
 * An agreement with its own project EDU (with a budget line), a project SHARED that another agreement also uses, an
 * instalment and a signed file; and that other agreement.
 *
 * @return array{0: Contract, 1: Contract}
 */
function cdAgreements(): array
{
    $donor = Donor::query()->create([
        'code' => 'CARPL', 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس بولندا', 'type' => 'church_agency',
        'country' => 'Poland', 'currency' => 'EUR', 'is_active' => true,
    ]);
    $make = fn (string $number) => Contract::query()->create([
        'number' => $number, 'donor_id' => $donor->id, 'title' => 'Education '.$number, 'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28',
        'currency' => 'EUR', 'amount' => '10000', 'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'draft', 'created_by' => 1,
    ]);

    $agreement = $make('CON-2026-001');
    $other = $make('CON-2026-002');

    ContractProject::query()->create(['contract_id' => $agreement->id, 'project_code' => 'EDU', 'amount' => '6000']);
    ContractProject::query()->create(['contract_id' => $agreement->id, 'project_code' => 'SHARED', 'amount' => '4000']);
    ContractProject::query()->create(['contract_id' => $other->id, 'project_code' => 'SHARED', 'amount' => '10000']);
    ContractInstalment::query()->create(['contract_id' => $agreement->id, 'number' => 1, 'due_on' => '2026-03-01', 'amount' => '10000', 'is_conditional' => false]);
    GlBudgetLine::query()->create(['key' => 'EDU|Teachers', 'project_code' => 'EDU', 'name' => 'Teachers', 'account_code' => '5101', 'budget' => 6000, 'currency' => 'EUR']);
    GlBudgetLine::query()->create(['key' => 'SHARED|Rent', 'project_code' => 'SHARED', 'name' => 'Rent', 'account_code' => '5101', 'budget' => 4000, 'currency' => 'EUR']);

    Storage::disk('local')->put('contract-files/'.$agreement->id.'/signed.pdf', 'pdf');
    $agreement->update(['signed_file_path' => 'contract-files/'.$agreement->id.'/signed.pdf', 'signed_file_name' => 'signed.pdf']);

    return [$agreement, $other];
}

it('deletes an agreement with its own projects, budget lines, instalments and files, keeps a shared project, and logs it', function () {
    [$agreement, $other] = cdAgreements();
    $manager = cdUser(RoleId::FinancialManager, 'Fadi');

    Livewire::actingAs($manager)->test(ContractPage::class, ['contract' => $agreement])
        ->assertSeeHtml('data-test="delete-agreement"')
        ->call('deleteAgreement')
        ->assertHasNoErrors()
        ->assertRedirect(route('funding.agreements'));

    expect(Contract::query()->find($agreement->id))->toBeNull()
        ->and(Contract::query()->find($other->id))->not->toBeNull()
        ->and(ContractProject::query()->where('contract_id', $agreement->id)->count())->toBe(0)
        ->and(ContractInstalment::query()->where('contract_id', $agreement->id)->count())->toBe(0)
        ->and(GlProject::query()->find('EDU'))->toBeNull()
        ->and(GlBudgetLine::query()->find('EDU|Teachers'))->toBeNull()
        ->and(GlProject::query()->find('SHARED'))->not->toBeNull()
        ->and(GlBudgetLine::query()->find('SHARED|Rent'))->not->toBeNull();

    Storage::disk('local')->assertMissing('contract-files/'.$agreement->id.'/signed.pdf');

    $entry = ContractAudit::query()->where('action', 'agreement_deleted')->sole();

    expect($entry->contract_number)->toBe('CON-2026-001')
        ->and((int) $entry->changed_by)->toBe((int) $manager->getKey())
        ->and($entry->details['projects_deleted'])->toBe(['EDU'])
        ->and($entry->details['projects_kept'])->toBe(['SHARED'])
        ->and($entry->details['budget_lines_deleted'])->toBe(1);

    Livewire::actingAs($manager)->test(Contracts::class)->assertDontSee('Education CON-2026-001')->assertSee('Education CON-2026-002');
});

it('refuses to delete an agreement whose own project or budget line the ledger already uses', function () {
    [$agreement] = cdAgreements();
    DB::table('gl_vouchers')->insert(['id' => 'JV-2026-0001', 'type' => 'JV', 'date' => '2026-04-01', 'status' => 'approved', 'prepared_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('gl_voucher_lines')->insert([
        'voucher_id' => 'JV-2026-0001', 'line_no' => 1, 'account_code' => '5101', 'currency' => 'EUR', 'rate' => 1, 'project_code' => 'EDU', 'budget_line_key' => 'EDU|Teachers',
        'debit_orig' => 10, 'credit_orig' => 0, 'debit_usd' => 11.5, 'credit_usd' => 0, 'debit_donor' => 0, 'credit_donor' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs(cdUser(RoleId::FinancialManager, 'Fadi'))->test(ContractPage::class, ['contract' => $agreement])
        ->call('deleteAgreement')
        ->assertNoRedirect()
        ->assertSee('Vouchers use the projects EDU: the agreement cannot be deleted.')
        ->assertSee('Vouchers use the budget lines EDU|Teachers: the agreement cannot be deleted.');

    expect(Contract::query()->find($agreement->id))->not->toBeNull()
        ->and(GlProject::query()->find('EDU'))->not->toBeNull()
        ->and(ContractAudit::query()->count())->toBe(0);
});

it('lets only the roles allowed to delete agreements delete one', function (RoleId $role) {
    [$agreement] = cdAgreements();

    Livewire::actingAs(cdUser($role, 'Someone'))->test(ContractPage::class, ['contract' => $agreement])
        ->assertDontSeeHtml('data-test="delete-agreement"')
        ->call('deleteAgreement')
        ->assertForbidden();

    expect(Contract::query()->find($agreement->id))->not->toBeNull();
})->with([RoleId::Accountant, RoleId::GeneralManager]);
