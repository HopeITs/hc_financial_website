<?php

use App\Enums\RoleId;
use App\Livewire\ProjectPage;
use App\Livewire\Projects;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    $legacy = DB::connection('legacy');

    $legacy->table('city')->insert([['ID' => 1, 'Name' => 'Aleppo'], ['ID' => 4, 'Name' => 'Homs']]);
    $legacy->table('donor')->insert([['ID' => 1, 'Name' => 'Donor One'], ['ID' => 2, 'Name' => 'Donor Two']]);
    $legacy->table('project')->insert([['ID' => 1, 'Name' => 'Hope Center'], ['ID' => 2, 'Name' => 'Study Zone']]);
    $legacy->table('subproject')->insert([
        ['ID' => 1, 'Name' => 'HC Aleppo', 'Project_ID' => 1, 'City_ID' => 1],
        ['ID' => 2, 'Name' => 'SZ Homs', 'Project_ID' => 2, 'City_ID' => 4],
    ]);
    $legacy->table('account')->insert([
        ['ID' => 10, 'SubProject_ID' => 1, 'Name' => 'Alpha Aleppo', 'IsClosed' => 0, 'city_id' => 1],
        ['ID' => 20, 'SubProject_ID' => 2, 'Name' => 'Beta Homs', 'IsClosed' => 0, 'city_id' => 4],
        ['ID' => 30, 'SubProject_ID' => 2, 'Name' => 'Gamma Homs', 'IsClosed' => 1, 'city_id' => 4],
    ]);
    $legacy->table('account_donor')->insert([['Account_id' => 10, 'Donor_id' => 1], ['Account_id' => 20, 'Donor_id' => 2]]);

    $balance = fn (int $cash, string $cashName, int $account, string $name, float $syp, float $usd) => $legacy->table('cash_balances')->insert([
        'CashAccount_id' => $cash, 'CashAccount_Name' => $cashName, 'City_ID' => 1, 'City_Name' => 'x', 'Subproject_Name' => 'x',
        'Account_ID' => $account, 'Account_Name' => $name, 'Account_Net' => $syp, 'Account_Net_USD' => $usd, 'Account_Net_New' => 0,
    ]);
    $balance(1, 'Safe A', 10, 'Alpha Aleppo', 1000.50, 10);
    $balance(2, 'Safe B', 10, 'Alpha Aleppo', 500, 5.25);
    $balance(1, 'Safe A', 20, 'Beta Homs', 200, 20);
    $balance(1, 'Safe A', 30, 'Gamma Homs', 7, 7);

    $legacy->table('line')->insert(['ID' => 1, 'Name' => 'Salaries']);
    $legacy->table('budget')->insert(['ID' => 1, 'Name' => 'Budget 2026', 'Account_ID' => 10]);
    $legacy->table('budget_line')->insert(['ID' => 1, 'Budget_ID' => 1, 'Line_ID' => 1, 'Unit' => 2, 'Timing' => 3, 'Price' => 150.25]);

    $legacy->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);
    $legacy->table('till')->insert(['ID' => 1, 'Type_ID' => 2, 'Account_ID' => 10]);
    $legacy->table('action')->insert([
        ['ID' => 1, 'Till_ID' => 1, 'AType_ID' => 1, 'Amount' => 100, 'ActionDate' => '2026-03-05', 'deleted_at' => null],
        ['ID' => 2, 'Till_ID' => 1, 'AType_ID' => 1, 'Amount' => 50, 'ActionDate' => '2026-03-20', 'deleted_at' => null],
        ['ID' => 3, 'Till_ID' => 1, 'AType_ID' => 4, 'Amount' => 30, 'ActionDate' => '2026-04-02', 'deleted_at' => null],
        ['ID' => 4, 'Till_ID' => 1, 'AType_ID' => 4, 'Amount' => 999, 'ActionDate' => '2026-04-03', 'deleted_at' => '2026-04-04'],
    ]);
});

function projectsUser(RoleId $role = RoleId::HeadOffice): User
{
    return User::query()->findOrFail(createFinanceUser('person'.$role->value, 'secret pass', $role->name, $role->value));
}

it('lists every project with city, program, donor, status and summed balances', function () {
    $this->actingAs(projectsUser())->get('/screens/projects')
        ->assertOk()
        ->assertSee('Alpha Aleppo')->assertSee('Hope Center')->assertSee('Donor One')
        ->assertSee('1,500.50')->assertSee('15.25')
        ->assertSee('Gamma Homs');
});

it('filters by city, program, donor, status and search', function () {
    $names = fn (array $filters) => collect(
        Livewire::actingAs(projectsUser())->test(Projects::class)->fill($filters)->viewData('projects')->items()
    )->pluck('name')->all();

    expect($names(['cityId' => 1]))->toBe(['Alpha Aleppo'])
        ->and($names(['program' => 'legacy:2']))->toBe(['Beta Homs', 'Gamma Homs'])
        ->and($names(['donorId' => 2]))->toBe(['Beta Homs'])
        ->and($names(['status' => 'closed']))->toBe(['Gamma Homs'])
        ->and($names(['status' => 'active']))->toBe(['Alpha Aleppo', 'Beta Homs'])
        ->and($names(['search' => 'beta']))->toBe(['Beta Homs']);
});

it('paginates on the server', function () {
    foreach (range(100, 120) as $id) {
        DB::connection('legacy')->table('account')->insert(['ID' => $id, 'SubProject_ID' => 1, 'Name' => "Extra $id"]);
        DB::connection('legacy')->table('cash_balances')->insert(['CashAccount_id' => 1, 'Account_ID' => $id, 'Account_Name' => "Extra $id"]);
    }

    $projects = Livewire::actingAs(projectsUser())->test(Projects::class)->viewData('projects');

    expect($projects->total())->toBe(24)->and($projects->items())->toHaveCount(15);
});

it('shows the four tabs of a project', function () {
    $page = Livewire::actingAs(projectsUser())->test(ProjectPage::class, ['account' => 10]);

    $page->assertSee('1,500.50')->assertSee('Safe A')->assertSee('Safe B')->assertSee('1,000.50');

    $page->call('show', 'accounts')->assertSee('Alpha Aleppo')->assertSee('Donor One');
    $page->call('show', 'budget')->assertSee('Salaries')->assertSee('901.50')->assertSee('150.25');
    $page->call('show', 'lifetime')->assertSee('2026-03')->assertSee('150.00')->assertSee('30.00')->assertDontSee('999.00');
});

it('answers 403 on a project that is not the manager\'s own and 404 on an unknown one', function () {
    $manager = projectsUser(RoleId::ProjectManager);
    DB::table('web_project_managers')->insert(['user_id' => $manager->getKey(), 'account_id' => 10]);

    $this->actingAs($manager)->get('/projects/10')->assertOk();
    $this->actingAs($manager)->get('/projects/20')->assertForbidden();
    $this->actingAs(projectsUser())->get('/projects/999')->assertNotFound();

    $names = collect(Livewire::actingAs($manager)->test(Projects::class)->viewData('projects')->items())->pluck('name')->all();
    expect($names)->toBe(['Alpha Aleppo']);
});

it('keeps the names but hides the money from a role without finance permission', function () {
    $manager = projectsUser(RoleId::ProjectManager);
    DB::table('web_project_managers')->insert(['user_id' => $manager->getKey(), 'account_id' => 10]);

    $this->actingAs($manager)->get('/screens/projects')
        ->assertOk()->assertSee('Alpha Aleppo')->assertDontSee('1,500.50')->assertDontSee(__('home.balance_usd'));

    $page = Livewire::actingAs($manager)->test(ProjectPage::class, ['account' => 10]);
    $page->assertSee('Alpha Aleppo')->assertDontSee('1,500.50')->assertDontSee('Safe A');
    $page->call('show', 'budget')->assertSee('Salaries')->assertDontSee('150.25')->assertDontSee('901.50');
    $page->call('show', 'lifetime')->assertDontSee('150.00')->assertSee(__('projects.money_hidden'));
});
