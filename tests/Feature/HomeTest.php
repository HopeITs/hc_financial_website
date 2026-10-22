<?php

use App\Contracts\ProjectBalanceSource;
use App\Enums\RoleId;
use App\Livewire\Home;
use App\Models\User;
use App\Services\Projects\LegacyProjectBalanceSource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();

    $row = fn (int $cash, int $account, string $name, int $city, string $cityName, float $syp, float $usd = 0, int $closed = 0, float $new = 0) => DB::connection('legacy')->table('cash_balances')->insert([
        'CashAccount_id' => $cash, 'City_ID' => $city, 'City_Name' => $cityName, 'Subproject_Name' => "Sub $cityName",
        'Account_ID' => $account, 'Account_Name' => $name, 'account_IsClosed' => $closed,
        'Account_Net' => $syp, 'Account_Net_USD' => $usd, 'Account_Net_New' => $new,
    ]);

    // Project 10 holds money in two cash accounts: its balance is the sum.
    $row(1, 10, 'Alpha Aleppo', 1, 'Aleppo', 1000.50, 0);
    $row(2, 10, 'Alpha Aleppo', 1, 'Aleppo', 2000.25, 300.10, new: 5);
    $row(1, 20, 'Beta Homs', 4, 'Homs', 500, 50);
    $row(1, 30, 'Closed Homs', 4, 'Homs', 999, 999, closed: 1);

    DB::connection('legacy')->table('tree_view')->insert([
        ['CityID' => 1, 'CityName' => 'Aleppo', 'ProjectName' => 'Hope Center', 'SubprojectName' => 'HC Aleppo', 'AccountID' => 10, 'AccountName' => 'Alpha Aleppo', 'IsClosed' => 0],
        ['CityID' => 1, 'CityName' => 'Aleppo', 'ProjectName' => 'Hope Center', 'SubprojectName' => 'HC Aleppo', 'AccountID' => 10, 'AccountName' => 'Alpha Aleppo', 'IsClosed' => 0],
        ['CityID' => 4, 'CityName' => 'Homs', 'ProjectName' => 'Hope Center', 'SubprojectName' => 'HC Homs', 'AccountID' => 30, 'AccountName' => 'Closed Homs', 'IsClosed' => 1],
    ]);
});

function homeUser(RoleId $role = RoleId::HeadOffice): User
{
    return User::query()->findOrFail(createFinanceUser('person'.$role->value, 'secret pass', $role->name, $role->value));
}

it('lists the active projects with summed balances and hides closed ones', function () {
    $this->actingAs(homeUser())->get('/home')
        ->assertOk()
        ->assertSee('Alpha Aleppo')
        ->assertSee('3,000.75')
        ->assertSee('300.10')
        ->assertSee('Beta Homs')
        ->assertDontSee('Closed Homs');
});

it('filters the projects by city', function () {
    Livewire::actingAs(homeUser())->test(Home::class)
        ->assertSee('3,000.75')->assertSee('500.00')
        ->set('cityId', 4)
        ->assertSee('500.00')->assertDontSee('3,000.75')
        ->call('filterCity', 4)
        ->assertSee('3,000.75');

    // A city without active projects (or unknown) filters nothing.
    Livewire::actingAs(homeUser())->test(Home::class)->set('cityId', 99)->assertSee('3,000.75')->assertSee('500.00');
});

it('keeps the balances behind the ProjectBalanceSource interface', function () {
    expect(app(ProjectBalanceSource::class))->toBeInstanceOf(LegacyProjectBalanceSource::class);

    $projects = app(ProjectBalanceSource::class)->activeProjects();

    expect($projects->pluck('name')->all())->toBe(['Alpha Aleppo', 'Beta Homs'])
        ->and($projects->first()->syp)->toBe('3000.75')
        ->and($projects->first()->usd)->toBe('300.10')
        ->and($projects->first()->sypNew)->toBe('5.00');
});

it('shows each account once in the sidebar tree and only the active ones', function () {
    $html = $this->actingAs(homeUser())->get('/home')->getContent();

    expect(substr_count($html, 'Alpha Aleppo'))->toBe(2) // the table row and one tree leaf
        ->and($html)->toContain('HC Aleppo')
        ->and($html)->not->toContain('HC Homs');
});

it('opens home for every legacy role', function (RoleId $role) {
    $this->actingAs(homeUser($role))->get('/home')->assertOk();
})->with(array_filter(RoleId::cases(), fn (RoleId $role) => ! $role->isWebOnly()));

it('refuses a person who has no role', function () {
    $id = DB::table('user')->insertGetId(['Username' => 'norole', 'Password' => 'x', 'Role' => null]);

    $this->actingAs(User::query()->findOrFail($id))->get('/home')->assertForbidden();
});

it('links the logo to home', function () {
    $this->actingAs(homeUser())->get('/home')->assertSee('data-test="home-link"', false)->assertSee(route('home'), false);
});
