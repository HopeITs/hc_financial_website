<?php

use App\Enums\ContractClosingOutcome;
use App\Enums\ContractStatus;
use App\Enums\RoleId;
use App\Livewire\ContractPage;
use App\Livewire\Contracts;
use App\Livewire\Donors;
use App\Models\Contract;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\Donor;
use App\Models\GlProject;
use App\Models\User;
use App\Models\WebUserRole;
use App\Support\Navigation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    Carbon::setTestNow('2026-11-16 10:00:00');

    GlProject::query()->create(['code' => 'F2F', 'name' => 'Face to Face', 'restriction' => 'With donor restrictions', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false, 'legacy_account_id' => 10]);
    GlProject::query()->create(['code' => 'SZ', 'name' => 'Study Zone', 'restriction' => 'With donor restrictions', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false, 'legacy_account_id' => 20]);
});

function dcUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

function dcDonor(string $code = 'CARPL', string $currency = 'EUR'): Donor
{
    return Donor::query()->create([
        'code' => $code, 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس بولندا', 'type' => 'church_agency',
        'country' => 'Poland', 'currency' => $currency, 'is_active' => true,
    ]);
}

/**
 * An EUR 120,000 draft contract, optionally with its two project shares (80,000 / 40,000) and three instalments
 * (60,000 unconditional, then 36,000 and 24,000 conditional).
 */
function dcContract(bool $complete = true): Contract
{
    $contract = Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => dcDonor()->id, 'title' => 'Education support 2026',
        'starts_on' => '2026-03-01', 'ends_on' => '2027-02-28', 'currency' => 'EUR', 'amount' => '120000',
        'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'draft', 'created_by' => 1,
    ]);

    if ($complete) {
        ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'F2F', 'amount' => '80000']);
        ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'SZ', 'amount' => '40000']);
        ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 1, 'due_on' => '2026-03-01', 'amount' => '60000', 'is_conditional' => false]);
        ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 2, 'due_on' => '2026-11-10', 'amount' => '36000', 'condition' => 'After the interim report is accepted', 'is_conditional' => true]);
        ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 3, 'due_on' => '2027-03-31', 'amount' => '24000', 'condition' => 'After the final report is accepted', 'is_conditional' => true]);
    }

    return $contract;
}

// Donors

it('opens the donors screen in place of the placeholder and lists the donors', function () {
    dcDonor();

    $this->actingAs(dcUser(RoleId::Accountant, 'Ann'))->get('/people/donors')
        ->assertOk()
        ->assertSee('Caritas Poland')
        ->assertSee('CARPL');
});

it('lets the financial manager add a donor with its contact', function () {
    Livewire::actingAs(dcUser(RoleId::FinancialManager, 'Fadi'))->test(Donors::class)
        ->call('add')
        ->set('nameEn', 'Mennonite Central Committee')
        ->set('nameAr', 'MCC')
        ->set('code', 'mcc')
        ->set('type', 'foundation')
        ->set('country', 'Canada')
        ->set('currency', 'USD')
        ->set('contactEmail', 'grants@example.org')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertSee('Donor Mennonite Central Committee added.');

    $donor = Donor::query()->where('code', 'MCC')->firstOrFail();

    expect($donor->country)->toBe('Canada')
        ->and($donor->contact_email)->toBe('grants@example.org')
        ->and($donor->contact_phone)->toBeNull()
        ->and($donor->is_active)->toBeTrue();
});

it('refuses a second donor with the same code and a bad email', function () {
    dcDonor();

    Livewire::actingAs(dcUser(RoleId::FinancialManager, 'Fadi'))->test(Donors::class)
        ->call('add')
        ->set('nameEn', 'Other')->set('nameAr', 'آخر')->set('code', 'carpl')->set('type', 'foundation')
        ->set('contactEmail', 'not an email')
        ->call('save')
        ->assertHasErrors(['code' => 'unique', 'contactEmail' => 'email']);

    expect(Donor::query()->count())->toBe(1);
});

it('lets the accountant read the donors but not add or edit one', function () {
    $donor = dcDonor();

    Livewire::actingAs(dcUser(RoleId::Accountant, 'Ann'))->test(Donors::class)
        ->assertSee('Caritas Poland')
        ->assertDontSeeHtml('data-test="add-donor"')
        ->call('edit', $donor->id)
        ->assertForbidden();
});

it('keeps the cashier out of the donors screen', function () {
    $this->actingAs(dcUser(RoleId::Cashier, 'Cara'))->get('/people/donors')->assertForbidden();
});

it('shows funding contracts in the projects menu of the financial manager but not of the cashier', function () {
    expect(__('navigation.screens.contracts'))->toBe('Funding contracts')
        ->and(Navigation::visibleGroups(dcUser(RoleId::FinancialManager, 'Fadi'))['projects'])->toContain('contracts')
        ->and(collect(Navigation::visibleGroups(dcUser(RoleId::Cashier, 'Cara')))->flatten())->not->toContain('contracts');
});

// Contracts

it('registers a new contract as a draft and opens its page', function () {
    $donor = dcDonor();

    Livewire::actingAs(dcUser(RoleId::Accountant, 'Ann'))->test(Contracts::class)
        ->call('add')
        ->set('donorId', (string) $donor->id)
        ->assertSet('currency', 'EUR')
        ->set('title', 'Education support 2026')
        ->set('startsOn', '2026-03-01')
        ->set('endsOn', '2027-02-28')
        ->set('amount', '120000')
        ->set('fixedRate', '1.15')
        ->call('save')
        ->assertHasNoErrors();

    $contract = Contract::query()->firstOrFail();

    expect($contract->number)->toBe('CON-2026-001')
        ->and($contract->status)->toBe(ContractStatus::Draft)
        ->and($contract->isApproved())->toBeFalse()
        ->and($contract->currency)->toBe('EUR')
        ->and((string) $contract->fixed_rate)->toBe('1.150000');
});

it('numbers contracts per year of their start', function () {
    dcContract(complete: false);

    expect(Contract::nextNumber(2026))->toBe('CON-2026-002')
        ->and(Contract::nextNumber(2027))->toBe('CON-2027-001');
});

it('refuses approval until the project shares and the instalments add up to the contract', function () {
    $contract = dcContract(complete: false);
    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'F2F', 'amount' => '80000']);

    Livewire::actingAs(dcUser(RoleId::FinancialManager, 'Fadi'))->test(ContractPage::class, ['contract' => $contract])
        ->call('approve')
        ->assertSee('The project shares add up to 80000.00, not to the contract amount 120000.00.')
        ->assertSee('Add at least one instalment.');

    expect($contract->refresh()->isApproved())->toBeFalse();
});

it('lets the financial manager approve a complete contract, and then fixes its shares', function () {
    $contract = dcContract();
    $manager = dcUser(RoleId::FinancialManager, 'Fadi');

    Livewire::actingAs($manager)->test(ContractPage::class, ['contract' => $contract])
        ->call('approve')
        ->assertSee('Contract approved.')
        ->set('tab', 'projects')
        ->call('removeShare', $contract->projects()->first()->id)
        ->assertSee('The contract is approved: its project shares and instalments are fixed.');

    $contract->refresh();

    expect($contract->isApproved())->toBeTrue()
        ->and($contract->approved_by)->toBe($manager->getKey())
        ->and($contract->status)->toBe(ContractStatus::Signed)
        ->and($contract->projects()->count())->toBe(2);
});

it('does not let the accountant approve a contract', function () {
    $contract = dcContract();

    Livewire::actingAs(dcUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->assertDontSeeHtml('data-test="approve-contract"')
        ->call('approve')
        ->assertForbidden();
});

it('adds a project share and an instalment while the contract is a draft', function () {
    $contract = dcContract(complete: false);

    Livewire::actingAs(dcUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('shareProject', 'SZ')->set('shareAmount', '40000')->call('addShare')
        ->assertHasNoErrors()
        ->set('shareProject', 'SZ')->set('shareAmount', '1')->call('addShare')
        ->assertHasErrors(['shareProject' => 'unique'])
        ->set('instalmentDue', '2026-06-01')->set('instalmentAmount', '50000')->set('instalmentConditional', true)->call('addInstalment')
        ->assertHasErrors(['instalmentCondition' => 'required'])
        ->set('instalmentCondition', 'After the interim report')->call('addInstalment')
        ->assertHasNoErrors();

    $instalment = $contract->instalments()->firstOrFail();

    expect($contract->projects()->pluck('project_code')->all())->toBe(['SZ'])
        ->and($instalment->number)->toBe(1)
        ->and($instalment->is_conditional)->toBeTrue()
        ->and($instalment->isRecognised())->toBeFalse();
});

it('counts a conditional instalment as income only once its condition is met', function () {
    $contract = dcContract();
    $contract->update(['approved_by' => 1, 'approved_at' => now(), 'status' => 'running']);
    $second = $contract->instalments()->where('number', 2)->firstOrFail();

    $page = Livewire::actingAs(dcUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract]);

    expect($page->viewData('recognised'))->toBe('60000.00')
        ->and($page->viewData('lateCount'))->toBe(1)
        ->and($second->daysLate(Carbon::now()->toImmutable()))->toBe(6);

    $page->call('markConditionMet', $second->id);

    expect($second->refresh()->condition_met_on?->toDateString())->toBe('2026-11-16')
        ->and($page->viewData('recognised'))->toBe('96000.00')
        ->and($page->viewData('lateCount'))->toBe(0);
});

it('closes a contract with the donor approval, the financial manager first and the general manager after', function () {
    Storage::fake('local');
    $contract = dcContract();
    $contract->update(['approved_by' => 1, 'approved_at' => now(), 'status' => 'running']);
    $general = dcUser(RoleId::GeneralManager, 'Gina');

    Livewire::actingAs(dcUser(RoleId::Accountant, 'Ann'))->test(ContractPage::class, ['contract' => $contract])
        ->set('closingOutcome', ContractClosingOutcome::ReturnToDonor->value)
        ->set('closingNote', 'EUR 1,200 left, to return')
        ->call('requestClosing')
        ->assertHasErrors(['closingFile' => 'required'])
        ->set('closingFile', UploadedFile::fake()->create('donor-approval.pdf', 50, 'application/pdf'))
        ->call('requestClosing')
        ->assertHasNoErrors();

    $contract->refresh();
    expect($contract->isClosingRequested())->toBeTrue();
    Storage::disk('local')->assertExists((string) $contract->closing_file_path);

    Livewire::actingAs($general)->test(ContractPage::class, ['contract' => $contract])
        ->call('approveClosing')
        ->assertSee('This closing step is not yours');

    Livewire::actingAs(dcUser(RoleId::FinancialManager, 'Fadi'))->test(ContractPage::class, ['contract' => $contract])
        ->call('approveClosing')
        ->assertSee('Closing approved.');

    expect($contract->refresh()->status)->toBe(ContractStatus::Running);

    Livewire::actingAs($general)->test(ContractPage::class, ['contract' => $contract])
        ->call('approveClosing')
        ->assertSee('Closing approved.');

    expect($contract->refresh()->status)->toBe(ContractStatus::Ended)
        ->and($contract->isOpen())->toBeFalse();
});

it('shows a project manager only the contracts of their projects', function () {
    $contract = dcContract();
    $other = Contract::query()->create([
        'number' => 'CON-2026-002', 'donor_id' => $contract->donor_id, 'title' => 'Other', 'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31', 'currency' => 'USD', 'amount' => '1000', 'fixed_rate' => '1', 'restriction' => 'restricted',
        'status' => 'draft', 'created_by' => 1,
    ]);
    ContractProject::query()->create(['contract_id' => $other->id, 'project_code' => 'SZ', 'amount' => '1000']);

    expect(Contract::query()->visibleTo([10])->pluck('number')->all())->toBe(['CON-2026-001'])
        ->and(Contract::query()->visibleTo([20])->orderBy('number')->pluck('number')->all())->toBe(['CON-2026-001', 'CON-2026-002'])
        ->and(Contract::query()->visibleTo([99])->count())->toBe(0)
        ->and(Contract::query()->visibleTo(null)->count())->toBe(2);
});

it('downloads the signed contract and keeps the cashier out', function () {
    Storage::fake('local');
    $contract = dcContract();
    Storage::disk('local')->put('contract-files/'.$contract->id.'/signed.pdf', 'pdf');
    $contract->update(['signed_file_path' => 'contract-files/'.$contract->id.'/signed.pdf', 'signed_file_name' => 'contract.pdf']);

    $this->actingAs(dcUser(RoleId::Accountant, 'Ann'))
        ->get(route('projects.contracts.file', ['contract' => $contract->id, 'kind' => 'signed']))
        ->assertOk()
        ->assertDownload('contract.pdf');

    $this->actingAs(dcUser(RoleId::Cashier, 'Cara'))
        ->get(route('projects.contracts.file', ['contract' => $contract->id, 'kind' => 'signed']))
        ->assertForbidden();
});
