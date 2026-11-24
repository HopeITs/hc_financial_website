<?php

use App\Enums\RoleId;
use App\Livewire\ReportsHome;
use App\Models\User;
use App\Support\Navigation;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
});

function rhUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

it('shows every report the person may open in one page, in three parts', function () {
    Livewire::actingAs(rhUser(RoleId::Accountant, 'Ann'))->test(ReportsHome::class)
        ->assertSeeHtml('data-test="reports-financial"')
        ->assertSeeHtml('data-test="report-tb"')
        ->assertSeeHtml('data-test="reports-followup"');

    $this->actingAs(rhUser(RoleId::Cashier, 'Carl'))->get(route('reports'))->assertForbidden();
});

it('keeps the duplicated screens out of the menu, still open by their address', function () {
    $head = rhUser(RoleId::HeadOffice, 'Hana');
    $menu = collect(Navigation::visibleGroups($head))->flatten();

    expect($menu)->not->toContain('approval')->not->toContain('log')->not->toContain('employees')->not->toContain('rcash')
        ->and($menu)->toContain('reports')->toContain('opening')
        ->and(Navigation::visibleGroups($head)['settings'])->toContain('opening');
});
