<?php

use App\Enums\RoleId;
use App\Livewire\Chart;
use App\Models\GlAccount;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    $this->seed(ChartOfAccountsSeeder::class);
});

function chartUser(RoleId $role = RoleId::HeadOffice): User
{
    return User::query()->findOrFail(createFinanceUser('chart'.$role->value, 'secret pass', $role->name, $role->value));
}

it('seeds the mockup chart: five roots, every parent present', function () {
    expect(GlAccount::query()->count())->toBe(168)
        ->and(GlAccount::query()->whereNull('parent_code')->pluck('code')->all())->toBe(['1000', '2000', '3000', '4000', '5000'])
        ->and(GlAccount::query()->whereNotNull('parent_code')->whereNotIn('parent_code', GlAccount::query()->select('code'))->count())->toBe(0)
        ->and(GlAccount::query()->where('code', '1111')->value('name_en'))->toBe('Aleppo main safe – USD')
        ->and(GlAccount::query()->where('code', '1171')->value('name_en'))->toBe('Fransabank – USD');
});

it('can be seeded twice without duplicates', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    expect(GlAccount::query()->count())->toBe(168);
});

it('shows the five classes on the chart screen', function () {
    $this->actingAs(chartUser())->get('/screens/chart')
        ->assertOk()
        ->assertSee('ASSETS')
        ->assertSee('EXPENSES');
});

it('shows one account with its rules, tags and the accounts below it', function () {
    $this->actingAs(chartUser())->get('/screens/chart?account=1110')
        ->assertOk()
        ->assertSee('Aleppo office')
        ->assertSee('Aleppo main safe – USD')
        ->assertSee('مكتب حلب');

    $this->actingAs(chartUser())->get('/screens/chart?account=1111')
        ->assertOk()
        ->assertSee('Physical US dollars in the Aleppo safe')
        ->assertSee('Fund source');
});

it('puts only the chart tree in the sidebar and the other groups in the top bar', function () {
    $html = $this->actingAs(chartUser())->get('/screens/chart')->getContent();

    expect($html)->toContain('data-test="chart-tree"')
        ->and($html)->toContain('data-test="menu-entries"')
        ->and($html)->toContain('data-test="menu-reports"')
        ->and($html)->not->toContain('data-test="menu-chart"');
});

it('opens the chart for the roles that have it and refuses the others', function () {
    foreach ([RoleId::HeadOffice, RoleId::FinancialManager, RoleId::Accountant] as $role) {
        $this->actingAs(chartUser($role))->get('/screens/chart')->assertOk();
    }

    $this->actingAs(chartUser(RoleId::Cashier))->get('/screens/chart')->assertForbidden();
});

it('ignores an unknown account code', function () {
    Livewire::actingAs(chartUser())->withQueryParams(['account' => '9999'])->test(Chart::class)->assertOk();
});

it('hands the accounts to the sidebar tree as data', function () {
    $html = $this->actingAs(chartUser())->get('/screens/chart')->getContent();

    expect($html)->not->toContain('@js(')
        ->and($html)->toContain('Aleppo main safe')
        ->and($html)->toContain('1111');
});
