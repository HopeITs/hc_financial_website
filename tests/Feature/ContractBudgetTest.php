<?php

use App\Enums\RoleId;
use App\Livewire\ContractBudget;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\Donor;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Contracts\ContractBudgets;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();

    foreach ([['5101', 'expenses', 'Salaries', 'رواتب'], ['5201', 'expenses', 'Rent', 'إيجار'], ['1111', 'assets', 'Cash', 'نقد']] as [$code, $class, $en, $ar]) {
        GlAccount::query()->create([
            'code' => $code, 'parent_code' => null, 'level' => 'posting', 'class' => $class, 'name_en' => $en, 'name_ar' => $ar,
            'normal_side' => 'debit', 'currency' => 'USD', 'is_cash' => $class === 'assets', 'required_tags' => [], 'is_active' => true,
        ]);
    }

    GlProject::query()->create(['code' => 'EDU', 'name' => 'Education', 'restriction' => 'With donor restrictions', 'donor_currency' => 'EUR', 'donor_rate' => 1.15, 'is_general_account' => false]);
    GlProject::query()->create(['code' => 'OUT', 'name' => 'Outside', 'restriction' => 'With donor restrictions', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false]);
});

function cbUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** An EUR 10,000 draft agreement whose one project EDU has the whole amount as its share. */
function cbAgreement(): Contract
{
    $donor = Donor::query()->create([
        'code' => 'CARPL', 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس بولندا', 'type' => 'church_agency',
        'country' => 'Poland', 'currency' => 'EUR', 'is_active' => true,
    ]);

    $contract = Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => $donor->id, 'title' => 'Education 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28',
        'currency' => 'EUR', 'amount' => '10000', 'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'draft', 'created_by' => 1,
    ]);
    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'EDU', 'amount' => '10000']);

    return $contract;
}

it('adds budget lines to a project of the agreement, in its currency, and compares them with the share', function () {
    $contract = cbAgreement();

    $page = Livewire::actingAs(cbUser(RoleId::Accountant, 'Ann'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->set('lineProject', 'EDU')->set('lineName', ' Teachers ')->set('lineAccount', '5101')->set('lineAmount', '6000')
        ->call('addLine')
        ->assertHasNoErrors()
        ->assertSee('Budget line added.')
        ->assertSee('Budget does not equal the share yet')
        ->set('lineProject', 'EDU')->set('lineName', 'Rent')->set('lineAccount', '5201')->set('lineAmount', '4000')
        ->call('addLine')
        ->assertSee('Budget equals the share');

    $line = GlBudgetLine::query()->findOrFail('EDU|Teachers');

    expect($line->name)->toBe('Teachers')
        ->and($line->account_code)->toBe('5101')
        ->and($line->currency)->toBe('EUR')
        ->and($line->donor)->toBe('Caritas Poland')
        ->and($line->budget)->toBe('6000.00');

    $page->set('lineProject', 'EDU')->set('lineName', 'Teachers')->set('lineAccount', '5101')->set('lineAmount', '1')
        ->call('addLine')->assertHasErrors(['lineName'])
        ->set('lineProject', 'OUT')->set('lineName', 'Other')->set('lineAccount', '1111')->set('lineAmount', '1')
        ->call('addLine')->assertHasErrors(['lineProject' => 'in', 'lineAccount' => 'in']);

    expect(GlBudgetLine::query()->count())->toBe(2);
});

it('removes an unused budget line but keeps one that is already used', function () {
    $contract = cbAgreement();
    $accountant = cbUser(RoleId::Accountant, 'Ann');
    app(ContractBudgets::class)->add($contract, 'EDU', 'Teachers', '5101', '100');
    app(ContractBudgets::class)->add($contract, 'EDU', 'Rent', '5201', '50');

    Livewire::actingAs($accountant)->test(ContractBudget::class, ['contractId' => $contract->id])
        ->call('removeLine', 'EDU|Rent')
        ->assertSee('Budget line removed.');

    expect(GlBudgetLine::query()->find('EDU|Rent'))->toBeNull();

    $this->mock(ContractBudgets::class, fn ($mock) => $mock->shouldReceive('isUsed')->andReturnTrue());

    Livewire::actingAs($accountant)->test(ContractBudget::class, ['contractId' => $contract->id])
        ->call('removeLine', 'EDU|Teachers')
        ->assertSee('This budget line is already used by an entry, a request or a salary split, so it cannot be removed.');

    expect(GlBudgetLine::query()->find('EDU|Teachers'))->not->toBeNull();
});

it('fixes the budget once the agreement is approved, and lets only the preparing roles change it', function () {
    $contract = cbAgreement();

    Livewire::actingAs(cbUser(RoleId::GeneralManager, 'Gina'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->assertDontSeeHtml('data-test="budget-form"')
        ->set('lineProject', 'EDU')->set('lineName', 'X')->set('lineAccount', '5101')->set('lineAmount', '1')
        ->call('addLine')
        ->assertForbidden();

    $contract->update(['approved_by' => 1, 'approved_at' => now(), 'status' => 'running']);

    Livewire::actingAs(cbUser(RoleId::FinancialManager, 'Fadi'))->test(ContractBudget::class, ['contractId' => $contract->id])
        ->assertDontSeeHtml('data-test="budget-form"')
        ->set('lineProject', 'EDU')->set('lineName', 'Late')->set('lineAccount', '5101')->set('lineAmount', '1')
        ->call('addLine')
        ->assertSee('The agreement is approved: its budget is fixed.');

    expect(GlBudgetLine::query()->count())->toBe(0);
});
