<?php

use App\Enums\RoleId;
use App\Livewire\ReferenceList;
use App\Livewire\Users;
use App\Models\User;
use App\Support\FinancePassword;
use App\Support\Navigation;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();

    createMasterDataSchema();

    DB::connection('legacy')->table('city')->insert([['ID' => 1, 'Name' => 'Aleppo'], ['ID' => 2, 'Name' => 'Homs']]);
    DB::connection('legacy')->table('tilltypes')->insert([
        ['ID' => 2, 'Name' => 'USD', 'is_local_currency' => 0, 'base_divisor' => '1.000000'],
        ['ID' => 4, 'Name' => 'SYP', 'is_local_currency' => 1, 'base_divisor' => '100'],
    ]);
    DB::connection('legacy')->table('donor')->insert([['ID' => 1, 'Name' => 'Caritas Poland', 'Abb_Name' => 'CP'], ['ID' => 2, 'Name' => 'CDCS', 'Abb_Name' => null]]);
    DB::connection('legacy')->table('cashaccount')->insert([['id' => 1, 'Name' => 'Main Cash Till - Aleppo', 'currency_tilltype_id' => 4, 'city_id' => 1]]);
    DB::connection('legacy')->table('denominations')->insert([['id' => 1, 'name' => '500', 'currency_tilltype_id' => 4]]);
    DB::connection('legacy')->table('employee')->insert([['id' => 1, 'FirstName' => 'Maya', 'LastName' => 'Haddad', 'FatherName' => 'Samir', 'BeginDate' => '2024-01-01 00:00:00', 'EndDate' => null]]);
    DB::connection('legacy')->table('account')->insert([['ID' => 8, 'Name' => 'Family to Family', 'city_id' => 1, 'SubProject_ID' => 1]]);
    DB::connection('legacy')->table('fiscalbond')->insert([['ID' => 1, 'Name' => 'HC - Aleppo', 'Date' => '2024-01-01', 'Notes' => 'Yearly', 'Account_ID' => 8, 'Till_ID' => 14]]);
    DB::connection('legacy')->table('linecategorytype')->insert([['ID' => 2, 'Name' => 'Running Cost']]);
    DB::connection('legacy')->table('linecategory')->insert([['ID' => 1, 'Name' => 'Fuel', 'Type_ID' => 2, 'Kind' => 1]]);

    DB::table('role')->insertOrIgnore(['ID' => 4, 'Name' => 'Financial Manager']);
});

function masterUser(RoleId $role, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function seeLog(string $title, string $date, string $user = 'Mai'): void
{
    DB::table('log')->insert([
        'form_name' => 'Web_Login', 'title' => $title, 'affected_table' => '', 'affected_id' => '', 'details' => 'from test',
        'additionaldetails' => '', 'query' => '', 'date' => $date, 'user' => $user,
    ]);
}

it('shows each reference list with what the old program keeps', function (string $slug, array $expected) {
    seeLog('LOGIN', '2026-03-01');

    Livewire::actingAs(masterUser(RoleId::HeadOffice))->test(ReferenceList::class, ['screen' => $slug])
        ->assertSee(__('master.read_only'))
        ->assertSee(__('master.decision.'.$slug))
        ->assertSee($expected);
})->with([
    'donors' => ['donors', ['Caritas Poland', 'CP', 'CDCS']],
    'cities' => ['cities', ['Aleppo', 'Homs']],
    'currency' => ['currency', ['USD', 'SYP', '100']],
    'denoms' => ['denoms', ['500', 'SYP']],
    'employees' => ['employees', ['Maya Haddad', 'Samir', '2024-01-01']],
    'tills' => ['tills', ['Main Cash Till - Aleppo', 'SYP', 'Aleppo']],
    'bonds' => ['bonds', ['HC - Aleppo', 'Family to Family', 'Yearly']],
    'blcats' => ['blcats', ['Fuel', 'Running Cost']],
    'log' => ['log', ['LOGIN', 'Mai', '2026-03-01']],
]);

it('counts the projects and cash boxes of a city', function () {
    Livewire::actingAs(masterUser(RoleId::HeadOffice))->test(ReferenceList::class, ['screen' => 'cities'])
        ->assertSeeHtmlInOrder(['Aleppo', '1', '1', 'Homs', '0', '0']);
});

it('searches a list, and pages it', function () {
    foreach (range(3, 30) as $id) {
        DB::connection('legacy')->table('donor')->insert(['ID' => $id, 'Name' => 'Donor '.str_pad((string) $id, 2, '0', STR_PAD_LEFT), 'Abb_Name' => null]);
    }

    $page = Livewire::actingAs(masterUser(RoleId::HeadOffice))->test(ReferenceList::class, ['screen' => 'donors']);

    expect($page->viewData('page')->total())->toBe(30)
        ->and($page->viewData('page')->count())->toBe(25);

    $page->set('search', 'Donor 17')->assertSee('Donor 17')->assertDontSee('Donor 18')->assertDontSee('Caritas');
});

it('filters the log by date', function () {
    seeLog('OLD', '2026-01-05');
    seeLog('NEW', '2026-03-05');

    Livewire::actingAs(masterUser(RoleId::HeadOffice))->test(ReferenceList::class, ['screen' => 'log'])
        ->set('from', '2026-02-01')->assertSee('NEW')->assertDontSee('OLD')
        ->set('from', '')->set('to', '2026-02-01')->assertSee('OLD')->assertDontSee('NEW');
});

it('opens the reference screens for the roles that may see them and not for the others', function () {
    $this->actingAs(masterUser(RoleId::Accountant))->get('/people/donors')->assertOk();
    $this->actingAs(masterUser(RoleId::Cashier, 'Cass'))->get('/treasury/tills')->assertOk();
    $this->actingAs(masterUser(RoleId::Cashier, 'Cass2'))->get('/people/donors')->assertForbidden();
    $this->actingAs(masterUser(RoleId::Employee, 'Emp'))->get('/people/log')->assertForbidden();
});

it('sends the old project windows to the project list', function (string $slug) {
    $this->actingAs(masterUser(RoleId::HeadOffice))->get('/'.Navigation::path($slug))->assertOk();
})->with(['projwin', 'budgetlines', 'lifetime']);

it('lists the users with their roles and states', function () {
    $ann = masterUser(RoleId::Accountant, 'Ann');
    DB::table('user')->where('ID', $ann->getKey())->update(['State' => 0]);
    masterUser(RoleId::FinancialManager, 'Fay');

    Livewire::actingAs(masterUser(RoleId::HeadOffice, 'Hoa'))->test(Users::class)
        ->assertSee(['Ann', 'Fay', 'Hoa', __('master.users.inactive'), __('master.users.active')])
        ->set('search', 'Fay')->assertSee('Fay')->assertDontSee('Ann');
});

it('changes a password the way the old program stores it, and logs it without the password', function () {
    $head = masterUser(RoleId::HeadOffice, 'Hoa');
    $target = masterUser(RoleId::Accountant, 'Ann');
    $before = DB::table('user')->where('ID', $target->getKey())->value('Password');

    Livewire::actingAs($head)->test(Users::class)
        ->call('edit', $target->getKey())
        ->set('password', 'a new strong one')->set('confirmation', 'a new strong one')
        ->call('save')
        ->assertSet('failures', [])
        ->assertSet('editing', null)
        ->assertSet('notice', __('master.users.changed', ['name' => 'Ann']));

    $stored = DB::table('user')->where('ID', $target->getKey())->value('Password');

    expect($stored)->not->toBe($before)
        ->and(strlen($stored))->toBe(64)
        ->and(FinancePassword::verify('a new strong one', $stored))->toBeTrue()
        ->and(FinancePassword::verify('secret pass', $stored))->toBeFalse();

    $log = DB::table('log')->where('title', 'PASSWORD_CHANGED')->first();
    expect($log->affected_table)->toBe('user')
        ->and($log->details)->toBe('Ann')
        ->and($log->user)->toBe('Hoa')
        ->and(json_encode($log))->not->toContain('a new strong one');

    // Nothing else of the user changed.
    expect(DB::table('user')->where('ID', $target->getKey())->value('Username'))->toBe('Ann')
        ->and((int) DB::table('user')->where('ID', $target->getKey())->value('Role'))->toBe(RoleId::Accountant->value);
});

it('refuses a short password, two different passwords and an unknown user', function () {
    $head = masterUser(RoleId::HeadOffice, 'Hoa');
    $target = masterUser(RoleId::Accountant, 'Ann');
    $before = DB::table('user')->where('ID', $target->getKey())->value('Password');

    Livewire::actingAs($head)->test(Users::class)
        ->call('edit', $target->getKey())
        ->set('password', 'short')->set('confirmation', 'short')->call('save')
        ->assertSet('failures', [__('master.users.too_short', ['min' => Users::MIN_LENGTH])])
        ->set('password', 'long enough one')->set('confirmation', 'another one!!')->call('save')
        ->assertSet('failures', [__('master.users.mismatch')]);

    expect(DB::table('user')->where('ID', $target->getKey())->value('Password'))->toBe($before);

    Livewire::actingAs($head)->test(Users::class)->call('edit', 9999)->assertNotFound();
});

it('lets only those who may edit users change a password', function () {
    $manager = masterUser(RoleId::FinancialManager, 'Fay');
    $target = masterUser(RoleId::Accountant, 'Ann');
    $before = DB::table('user')->where('ID', $target->getKey())->value('Password');

    $this->actingAs($manager)->get('/people/users')->assertOk()->assertDontSee('data-test="change-'.$target->getKey().'"', false);
    Livewire::actingAs($manager)->test(Users::class)->call('edit', $target->getKey())->assertForbidden();
    Livewire::actingAs($manager)->test(Users::class)->set('editing', $target->getKey())->set('password', 'long enough one')->set('confirmation', 'long enough one')->call('save')->assertForbidden();

    expect(DB::table('user')->where('ID', $target->getKey())->value('Password'))->toBe($before);
    $this->actingAs(masterUser(RoleId::Accountant, 'Ann2'))->get('/people/users')->assertForbidden();
});

it('never writes to a legacy table from a reference screen', function () {
    $legacy = DB::connection('legacy');
    $counts = fn () => collect(['donor', 'city', 'tilltypes', 'cashaccount', 'employee', 'fiscalbond'])->map(fn (string $table) => $legacy->table($table)->count())->all();
    $before = $counts();

    foreach (['donors', 'cities', 'currency', 'denoms', 'employees', 'tills', 'bonds', 'blcats', 'log'] as $slug) {
        Livewire::actingAs(masterUser(RoleId::HeadOffice, 'U'.$slug))->test(ReferenceList::class, ['screen' => $slug])->set('search', 'x');
    }

    expect($counts())->toBe($before);
});
