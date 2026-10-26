<?php

use App\Enums\RoleId;
use App\Livewire\Home;
use App\Livewire\ProjectPage;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Access\LegacyDataScope;
use App\Services\Projects\LegacyProjectBalanceSource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config(['finance.scoped_roles' => ['ProjectManager', 'FinancialManager', 'Cashier', 'Accountant', 'Employee']]);
    createFinanceSchema();
    createLegacyHomeSchema();
    $legacy = DB::connection('legacy');

    $legacy->table('accounttype')->insert([['id' => 1, 'name' => 'Project'], ['id' => 2, 'name' => 'Exchange'], ['id' => 3, 'name' => 'Personal']]);
    $legacy->table('account')->insert([
        ['ID' => 10, 'SubProject_ID' => 1, 'Name' => 'Alpha', 'Accounttype_id' => 1],
        ['ID' => 11, 'SubProject_ID' => 1, 'Name' => 'Alpha two', 'Accounttype_id' => 1],
        ['ID' => 20, 'SubProject_ID' => 2, 'Name' => 'Beta', 'Accounttype_id' => 1],
        ['ID' => 90, 'SubProject_ID' => 0, 'Name' => 'Exchange desk', 'Accounttype_id' => 2],
        ['ID' => 91, 'SubProject_ID' => 0, 'Name' => 'Rana personal', 'Accounttype_id' => 3],
    ]);

    $row = fn (int $cash, int $account, string $name, float $syp) => $legacy->table('cash_balances')->insert([
        'CashAccount_id' => $cash, 'City_ID' => 1, 'City_Name' => 'Aleppo', 'Subproject_Name' => 'x',
        'Account_ID' => $account, 'Account_Name' => $name, 'Account_Net' => $syp, 'Account_Net_USD' => 0, 'Account_Net_New' => 0,
    ]);
    $row(1, 10, 'Alpha', 100);
    $row(2, 10, 'Alpha', 200);
    $row(1, 11, 'Alpha two', 50);
    $row(1, 20, 'Beta', 500);
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

function giveSubProject(User $user, int $subProject): void
{
    DB::connection('legacy')->table('user_accounts')->insert(['user_id' => $user->getKey(), 'subproject_id' => $subProject]);
}

function giveAccount(User $user, int $account): void
{
    DB::connection('legacy')->table('user_accounts')->insert(['user_id' => $user->getKey(), 'account_id' => $account]);
}

function giveCash(User $user, int $cashAccount): void
{
    DB::connection('legacy')->table('user_cashaccounts')->insert(['user_id' => $user->getKey(), 'cashaccount_id' => $cashAccount]);
}

it('applies no limit to head office, the general manager and donors', function (RoleId $role) {
    expect(app(LegacyDataScope::class)->for(scopedUser($role))->unrestricted)->toBeTrue();
})->with([RoleId::HeadOffice, RoleId::GeneralManager, RoleId::Donor]);

it('gives a financial manager, a project manager and a cashier the project accounts of their sub-projects', function (RoleId $role) {
    $user = scopedUser($role);
    giveSubProject($user, 1);
    giveCash($user, 2);

    $scope = app(LegacyDataScope::class)->for($user);

    expect($scope->unrestricted)->toBeFalse()
        ->and($scope->projectAccountIds)->toEqualCanonicalizing([10, 11])
        ->and($scope->cashAccountIds)->toBe([2])
        ->and($scope->allowsProject(20))->toBeFalse()
        ->and($scope->allowsCashAccount(1))->toBeFalse();
})->with([RoleId::FinancialManager, RoleId::ProjectManager, RoleId::Cashier]);

it('gives an accountant only the project accounts named for them, not the whole sub-project', function () {
    $user = scopedUser(RoleId::Accountant);
    giveSubProject($user, 2);
    giveAccount($user, 10);
    giveAccount($user, 90);
    giveAccount($user, 91);

    $scope = app(LegacyDataScope::class)->for($user);

    expect($scope->projectAccountIds)->toBe([10])
        ->and($scope->exchangeAccountIds)->toBe([90])
        ->and($scope->personalAccountIds)->toBe([91]);
});

it('gives an employee no project accounts', function () {
    $user = scopedUser(RoleId::Employee);
    giveSubProject($user, 1);
    giveAccount($user, 10);

    expect(app(LegacyDataScope::class)->for($user)->projectAccountIds)->toBe([]);
});

it('keeps the projects a manager was given on the web next to the Windows program\'s own', function () {
    $user = scopedUser(RoleId::ProjectManager);
    giveSubProject($user, 2);
    DB::table('web_project_managers')->insert(['user_id' => $user->getKey(), 'account_id' => 10]);

    expect(app(LegacyDataScope::class)->for($user)->projectAccountIds)->toEqualCanonicalizing([20, 10]);
});

it('joins the scopes of a person with two roles', function () {
    $user = scopedUser(RoleId::Accountant);
    WebUserRole::create(['user_id' => $user->getKey(), 'role_id' => RoleId::ProjectManager]);
    giveAccount($user, 10);
    giveSubProject($user, 2);

    expect(app(LegacyDataScope::class)->for($user)->projectAccountIds)->toEqualCanonicalizing([10, 20]);
});

it('shows a limited person only their own projects on the home page and nothing when none is given', function () {
    $user = scopedUser(RoleId::FinancialManager);
    giveSubProject($user, 2);

    Livewire::actingAs($user)->test(Home::class)->assertSee('Beta')->assertDontSee('Alpha');
    Livewire::actingAs(scopedUser(RoleId::FinancialManager, 'nobody'))->test(Home::class)->assertDontSee('Beta')->assertDontSee('Alpha');
    Livewire::actingAs(scopedUser(RoleId::HeadOffice, 'boss'))->test(Home::class)->assertSee('Beta')->assertSee('Alpha');
});

it('opens only the project pages in scope and lists only the cash accounts and siblings in scope', function () {
    $user = scopedUser(RoleId::Accountant);
    giveAccount($user, 10);
    giveCash($user, 1);

    $this->actingAs($user)->get('/projects/20')->assertForbidden();
    $this->actingAs($user)->get('/projects/11')->assertForbidden();

    $page = Livewire::actingAs($user)->test(ProjectPage::class, ['account' => 10]);
    expect($page->viewData('cashAccounts')->pluck('cashAccountId')->all())->toBe([1]);
    $page->call('show', 'accounts');
    expect($page->viewData('siblings')->pluck('accountId')->all())->toBe([10]);
});

it('filters the balance source by the given project and cash accounts', function () {
    $source = app(LegacyProjectBalanceSource::class);

    expect($source->activeProjects(null, [20])->pluck('accountId')->all())->toBe([20])
        ->and($source->activeProjects(null, [])->all())->toBe([])
        ->and($source->activeProjects()->pluck('accountId')->all())->toBe([10, 11, 20])
        ->and($source->byCashAccount(10, [2])->pluck('cashAccountId')->all())->toBe([2])
        ->and($source->byCashAccount(10)->pluck('cashAccountId')->all())->toBe([1, 2])
        ->and($source->cities([])->all())->toBe([]);
});

it('limits only project managers until the other roles are switched on', function () {
    config(['finance.scoped_roles' => ['ProjectManager']]);

    expect(app(LegacyDataScope::class)->for(scopedUser(RoleId::FinancialManager))->unrestricted)->toBeTrue()
        ->and(app(LegacyDataScope::class)->for(scopedUser(RoleId::Accountant, 'acc'))->unrestricted)->toBeTrue()
        ->and(app(LegacyDataScope::class)->for(scopedUser(RoleId::ProjectManager, 'pm'))->unrestricted)->toBeFalse();
});
