<?php

use App\Enums\RoleId;
use App\Livewire\Programs;
use App\Models\GlProgram;
use App\Models\Legacy\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    $legacy = DB::connection('legacy');
    $legacy->table('project')->insert([['ID' => 1, 'Name' => 'Hope Center'], ['ID' => 2, 'Name' => 'Study Zone']]);
    $legacy->table('subproject')->insert([['ID' => 1, 'Name' => 'HC Aleppo', 'Project_ID' => 1], ['ID' => 2, 'Name' => 'SZ Homs', 'Project_ID' => 2]]);
    $legacy->table('account')->insert([['ID' => 10, 'SubProject_ID' => 1], ['ID' => 20, 'SubProject_ID' => 2], ['ID' => 30, 'SubProject_ID' => 2]]);

    $balance = fn (int $account, string $name, float $syp, float $usd, int $closed = 0) => $legacy->table('cash_balances')->insert([
        'CashAccount_id' => 1, 'City_ID' => 1, 'City_Name' => 'Aleppo', 'Subproject_Name' => 'x',
        'Account_ID' => $account, 'Account_Name' => $name, 'account_IsClosed' => $closed,
        'Account_Net' => $syp, 'Account_Net_USD' => $usd, 'Account_Net_New' => 0,
    ]);
    $balance(10, 'Alpha', 1000.50, 10);
    $balance(10, 'Alpha', 500, 5.25);
    $balance(20, 'Beta', 200, 20);
    $balance(30, 'Closed', 999, 999, closed: 1);
});

function programsUser(RoleId $role = RoleId::HeadOffice): User
{
    return User::query()->findOrFail(createFinanceUser('person'.$role->value, 'secret pass', $role->name, $role->value));
}

it('lists the legacy programs with their active projects and balances', function () {
    $this->actingAs(programsUser())->get('/projects/programs')
        ->assertOk()
        ->assertSee('Hope Center')
        ->assertSee('1,500.50')
        ->assertSee('15.25')
        ->assertSee('Study Zone')
        ->assertSee('200.00');

    $rows = Livewire::actingAs(programsUser())->test(Programs::class)->viewData('rows');

    expect($rows->firstWhere('name', 'Hope Center')->activeProjects)->toBe(1)
        ->and($rows->firstWhere('name', 'Study Zone')->activeProjects)->toBe(1); // the closed account is not counted
});

it('lets Head Office and the Financial Manager add a program, shown with a badge', function (RoleId $role) {
    Livewire::actingAs(programsUser($role))->test(Programs::class)
        ->assertSee(__('programs.add'))
        ->call('openForm')
        ->set('nameEn', 'Water')->set('nameAr', 'المياه')->set('description', 'Clean water')->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Water')
        ->assertSee(__('programs.added_on_web'))
        ->assertDontSee('data-test="duplicate-warning"', false);

    expect(GlProgram::query()->count())->toBe(1)
        ->and(GlProgram::query()->first()->name_ar)->toBe('المياه');
})->with([RoleId::HeadOffice, RoleId::FinancialManager]);

it('validates the form', function () {
    Livewire::actingAs(programsUser())->test(Programs::class)
        ->call('openForm')
        ->set('nameEn', '')->set('nameAr', '')->set('status', 'bogus')
        ->call('save')
        ->assertHasErrors(['nameEn' => 'required', 'nameAr' => 'required', 'status']);

    expect(GlProgram::query()->count())->toBe(0);
});

it('refuses a duplicate name', function () {
    $before = GlProgram::query()->count();

    Livewire::actingAs(programsUser())->test(Programs::class)
        ->call('openForm')
        ->set('nameEn', 'hope center')->set('nameAr', 'مركز الأمل')
        ->call('save')
        ->assertHasErrors(['nameEn']);

    expect(GlProgram::query()->count())->toBe($before);
});

it('refuses the add button and the save to roles without programs.create', function (RoleId $role) {
    $user = programsUser($role);

    $this->actingAs($user)->get('/projects/programs')->assertOk()->assertDontSee('data-test="add-program"', false);

    Livewire::actingAs($user)->test(Programs::class)->call('openForm')->assertForbidden();
    Livewire::actingAs($user)->test(Programs::class)->set('nameEn', 'X')->set('nameAr', 'س')->call('save')->assertForbidden();
    Livewire::actingAs($user)->test(Programs::class)->set('linkProject', '1')->set('linkProgram', 'legacy:2')->call('link')->assertForbidden();

    expect(GlProgram::query()->count())->toBe(0);
})->with([RoleId::Accountant, RoleId::ProjectManager]);

it('refuses the page to a role that cannot view it', function () {
    $this->actingAs(programsUser(RoleId::Cashier))->get('/projects/programs')->assertForbidden();
});

it('files a project under a web program and moves its balances', function () {
    $program = GlProgram::query()->create(['name_en' => 'Water', 'name_ar' => 'المياه', 'status' => 'active']);

    $component = Livewire::actingAs(programsUser())->test(Programs::class)
        ->set('linkProject', '1')->set('linkProgram', 'web:'.$program->id)
        ->call('link')
        ->assertHasNoErrors();

    $rows = $component->viewData('rows');

    expect($rows->first(fn ($row) => $row->key() === 'web:'.$program->id)->usd)->toBe('15.25')
        ->and($rows->first(fn ($row) => $row->key() === 'web:'.$program->id)->activeProjects)->toBe(1)
        ->and($rows->firstWhere('name', 'Hope Center')->activeProjects)->toBe(0);

    $component->call('unlink', 1);

    expect(DB::table('gl_program_projects')->count())->toBe(0);
});

it('rejects a link to an unknown project or program', function () {
    Livewire::actingAs(programsUser())->test(Programs::class)
        ->set('linkProject', '99')->set('linkProgram', 'legacy:2')->call('link')->assertHasErrors('linkProject')
        ->set('linkProject', '1')->set('linkProgram', 'web:99')->call('link')->assertHasErrors('linkProgram');
});

it('never writes the legacy tables', function () {
    $before = DB::connection('legacy')->table('project')->get()->toArray();

    Livewire::actingAs(programsUser())->test(Programs::class)
        ->call('openForm')->set('nameEn', 'Water')->set('nameAr', 'المياه')->call('save')
        ->set('linkProject', '1')->set('linkProgram', 'web:1')->call('link');

    expect(DB::connection('legacy')->table('project')->get()->toArray())->toEqual($before)
        ->and(fn () => Project::query()->find(1)->update(['Name' => 'x']))->toThrow(LogicException::class);
});
