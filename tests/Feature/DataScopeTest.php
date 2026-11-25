<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\Home;
use App\Livewire\Users;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Entries\CashVouchers;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Projects\ActiveProjectBalances;
use App\Services\Projects\ProjectAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 * Which projects a person sees, kept by the web program since it left the Windows program (finance decision of
 * 2026-11-27): the projects given on the users screen, by code.
 */

beforeEach(function () {
    config(['finance.scoped_roles' => ['ProjectManager', 'FinancialManager']]);
    createFinanceSchema();
    createLegacyHomeSchema();
    seedChartWithTestProjects();
});

function scopedUser(RoleId $role, string $name = 'scoped'): User
{
    $legacyRole = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacyRole->name, $legacyRole->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

it('applies no limit to head office, the general manager and donors', function (RoleId $role) {
    expect(app(ProjectAccess::class)->limitedTo(scopedUser($role)))->toBeNull();
})->with([RoleId::HeadOffice, RoleId::GeneralManager, RoleId::Donor]);

it('limits a project manager to the projects given to them, and to nothing when none is given', function () {
    $pam = scopedUser(RoleId::ProjectManager, 'Pam');
    giveProjects((int) $pam->getKey(), ['SZ', 'F2F']);
    $access = app(ProjectAccess::class);

    expect($access->limitedTo($pam))->toBe(['F2F', 'SZ'])
        ->and($access->canOpen($pam, 'SZ'))->toBeTrue()
        ->and($access->canOpen($pam, 'CORE'))->toBeFalse()
        ->and($access->limitedTo(scopedUser(RoleId::ProjectManager, 'Nobody')))->toBe([]);

    // Giving again replaces the list.
    $access->give((int) $pam->getKey(), ['CORE']);
    expect($access->limitedTo($pam))->toBe(['CORE']);
});

it('limits only the roles switched on, and a second role that is not limited lifts the limit', function () {
    config(['finance.scoped_roles' => ['ProjectManager']]);
    $access = app(ProjectAccess::class);
    $both = scopedUser(RoleId::ProjectManager, 'Both');
    WebUserRole::create(['user_id' => $both->getKey(), 'role_id' => RoleId::Accountant]);

    expect($access->limitedTo(scopedUser(RoleId::FinancialManager, 'Fadi')))->toBeNull()
        ->and($access->limitedTo(scopedUser(RoleId::ProjectManager, 'Pam')))->toBe([])
        ->and($access->limitedTo($both))->toBeNull();
});

it('shows each active project\'s cash from the approved vouchers, and a limited person only their own', function () {
    ledgerApprovedVoucher();
    ledgerApprovedVoucher(lines: [
        new LineDraft('1111', 'USD', debit: 250, projectCode: 'SZ', fundSource: FundSource::DonorGrant),
        new LineDraft('4001', 'USD', credit: 250, projectCode: 'SZ'),
    ]);

    $rows = app(ActiveProjectBalances::class)->activeProjects()->keyBy('code');
    expect($rows['F2F']->usd)->toBe('100.00')
        ->and($rows['SZ']->usd)->toBe('250.00')
        ->and($rows['F2F']->city)->toBe('Aleppo')
        ->and(app(ActiveProjectBalances::class)->activeProjects(null, ['SZ'])->pluck('code')->all())->toBe(['SZ'])
        ->and(app(ActiveProjectBalances::class)->activeProjects(null, [])->all())->toBe([]);

    $fadi = scopedUser(RoleId::FinancialManager, 'Fadi');
    giveProjects((int) $fadi->getKey(), ['SZ']);

    Livewire::actingAs($fadi)->test(Home::class)->assertSee('Study Zone')->assertSee('250.00')->assertDontSee('Family to Family');
    Livewire::actingAs(scopedUser(RoleId::FinancialManager, 'Nobody'))->test(Home::class)->assertDontSee('Study Zone')->assertDontSee('Family to Family');
    Livewire::actingAs(scopedUser(RoleId::HeadOffice, 'Boss'))->test(Home::class)->assertSee('Study Zone')->assertSee('Family to Family');
});

it('limits an accountant switched on for it to their projects, in the pickers and when a voucher is saved', function () {
    $head = scopedUser(RoleId::HeadOffice, 'Hoa');
    $ann = scopedUser(RoleId::Accountant, 'Ann');
    giveProjects((int) $ann->getKey(), ['SZ']);

    // Linked to a project but not switched on: still sees and enters every project.
    expect(app(ProjectAccess::class)->limitedTo($ann))->toBeNull();
    app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => (int) $ann->getKey()]));

    Livewire::actingAs($head)->test(Users::class)
        ->call('selectUser', $ann->getKey())
        ->assertSeeHtml('data-test="limit-projects-box"')
        ->set('limitProjects', true)->call('saveAccess')
        ->assertSet('failures', []);

    app()->forgetInstance(ProjectAccess::class);
    $ann = $ann->fresh();
    expect(app(ProjectAccess::class)->limitedTo($ann))->toBe(['SZ']);

    $this->actingAs($ann);
    expect(app(CashVouchers::class)->projects()->keys()->all())->toBe(['SZ'])
        ->and(fn () => app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => (int) $ann->getKey()])))->toThrow(LedgerRuleException::class);

    app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => (int) $ann->getKey()], [
        new LineDraft('1111', 'USD', debit: 40, projectCode: 'SZ', fundSource: FundSource::DonorGrant),
        new LineDraft('4001', 'USD', credit: 40, projectCode: 'SZ'),
    ]));
});

it('never limits head office, whatever is switched on for them', function () {
    $boss = scopedUser(RoleId::HeadOffice, 'Boss');
    giveProjects((int) $boss->getKey(), ['SZ']);
    app(ProjectAccess::class)->switchTo((int) $boss->getKey(), true);

    expect(app(ProjectAccess::class)->limitedTo($boss->fresh()))->toBeNull();
    Livewire::actingAs(scopedUser(RoleId::HeadOffice, 'Hoa'))->test(Users::class)
        ->call('selectUser', $boss->getKey())
        ->assertDontSeeHtml('data-test="limit-projects-box"');
});

it('gives a person their projects on the users screen, and refuses a project that does not exist', function () {
    $head = scopedUser(RoleId::HeadOffice, 'Hoa');
    $pam = scopedUser(RoleId::ProjectManager, 'Pam');

    Livewire::actingAs($head)->test(Users::class)
        ->call('selectUser', $pam->getKey())
        ->assertSeeHtml('data-test="user-projects"')
        ->set('projectCodes', ['F2F', 'NOPE'])->call('saveAccess')
        ->assertSet('failures', [__('master.users.bad_project')])
        ->set('projectCodes', ['F2F'])->call('saveAccess')
        ->assertSet('failures', []);

    expect(app(ProjectAccess::class)->given((int) $pam->getKey()))->toBe(['F2F']);
});

it('copies the Windows program\'s links into the web\'s own list once, by project code', function () {
    DB::table('gl_projects')->where('code', 'F2F')->update(['legacy_account_id' => 10]);
    DB::table('gl_projects')->where('code', 'SZ')->update(['legacy_account_id' => 20]);
    DB::table('gl_projects')->where('code', 'CORE')->update(['legacy_account_id' => 30]);

    // The Windows program's tables, in the same database as the web's.
    Schema::create('accounttype', function ($table) {
        $table->integer('id');
        $table->string('name');
    });
    Schema::create('account', function ($table) {
        $table->integer('ID');
        $table->integer('SubProject_ID')->nullable();
        $table->integer('Accounttype_id')->nullable();
    });
    Schema::create('user_accounts', function ($table) {
        $table->integer('user_id');
        $table->integer('subproject_id')->nullable();
        $table->integer('account_id')->nullable();
    });
    DB::table('accounttype')->insert([['id' => 1, 'name' => 'Project']]);
    DB::table('account')->insert([['ID' => 10, 'SubProject_ID' => 1, 'Accounttype_id' => 1], ['ID' => 20, 'SubProject_ID' => 2, 'Accounttype_id' => 1], ['ID' => 30, 'SubProject_ID' => 3, 'Accounttype_id' => 1]]);

    $pam = scopedUser(RoleId::ProjectManager, 'Pam');
    $ann = scopedUser(RoleId::Accountant, 'Ann');
    DB::table('user_accounts')->insert([
        ['user_id' => $pam->getKey(), 'subproject_id' => 1, 'account_id' => null],
        // A named account counts only for an accountant.
        ['user_id' => $pam->getKey(), 'subproject_id' => null, 'account_id' => 30],
        ['user_id' => $ann->getKey(), 'subproject_id' => null, 'account_id' => 30],
    ]);
    DB::table('web_project_managers')->insert(['user_id' => $pam->getKey(), 'account_id' => 20]);

    $migration = require database_path('migrations/2026_11_28_090000_create_web_user_projects_table.php');
    $migration->up();
    $migration->up();

    expect(app(ProjectAccess::class)->given((int) $pam->getKey()))->toBe(['F2F', 'SZ'])
        ->and(app(ProjectAccess::class)->given((int) $ann->getKey()))->toBe(['CORE']);
});
