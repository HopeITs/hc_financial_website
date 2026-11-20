<?php

use App\Enums\RoleId;
use App\Models\User;
use App\Support\Navigation;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    seedChartWithTestProjects();
});

function tabsUser(): User
{
    return User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
}

it('puts the page tabs above every signed-in page, kept across pages, with the page\'s menu group for its icon', function () {
    $html = $this->actingAs(tabsUser())->get('/chart')->getContent();

    expect($html)->toContain('x-persist="page-tabs"')
        ->and($html)->toContain('data-test="page-tabs"')
        ->and($html)->toContain('data-page-tab data-group="chart"')
        ->and($html)->toContain('data-tab-icon="entries"')
        ->and($html)->toContain(__('common.tabs.close_others'));
});

it('names each menu screen by its address and group, for a tab opened in the background', function () {
    $screens = Navigation::tabScreens();

    expect($screens['home'])->toBe(['group' => 'home', 'label' => __('home.title')])
        ->and($screens[Navigation::path('single')])->toBe(['group' => 'entries', 'label' => __('navigation.screens.single')])
        ->and(Navigation::groupOf('tb'))->toBe('reports')
        ->and(Navigation::groupOf('nothing'))->toBeNull();
});
