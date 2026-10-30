<?php

use App\Enums\RoleId;
use App\Models\User;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
});

it('shows the app\'s own page for an unknown address, in the browser\'s language when there is no session', function () {
    $this->get('/no-such-page')->assertNotFound()
        ->assertSee('data-test="error-page"', false)
        ->assertSee(__('errors.404.title', [], 'en'))
        ->assertSee('data-test="error-home"', false);

    $this->withHeader('Accept-Language', 'ar,en;q=0.5')->get('/no-such-page')->assertNotFound()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('errors.404.title', [], 'ar'));

    // The language chosen in the app wins over the browser's, even for an address that matches no route.
    $this->withSession(['locale' => 'ar'])->get('/no-such-page')->assertNotFound()
        ->assertSee(__('errors.404.title', [], 'ar'));
});

it('shows a refused screen in the person\'s chosen language', function () {
    $employee = User::query()->findOrFail(createFinanceUser('Emp', 'secret pass', 'Employee', RoleId::Employee->value));

    $this->actingAs($employee)->withSession(['locale' => 'ar'])->get('/reports/trial-balance')->assertForbidden()
        ->assertSee('data-code="403"', false)
        ->assertSee(__('errors.403.title', [], 'ar'));
});

it('has a page for each error the app can show, the expired one sending the person to sign in', function (string $code) {
    $html = view('errors.'.$code)->render();

    expect($html)->toContain('data-code="'.$code.'"')->toContain(e(__('errors.'.$code.'.title')));

    if ($code === '419') {
        expect($html)->toContain('data-test="error-sign-in"')->toContain(route('login'));
    }
})->with(['403', '404', '419', '500', '503']);

it('replaces Livewire\'s English session-expired prompt on every signed-in page', function () {
    $head = User::query()->findOrFail(createFinanceUser('Ho', 'secret pass'));

    $this->actingAs($head)->get('/bridge/decisions')->assertOk()
        ->assertSee('data-test="session-expired"', false)
        ->assertSee(__('errors.expired.title'));
});

it('has every error text in English and Arabic', function () {
    $keys = fn (string $locale) => collect(require lang_path("$locale/errors.php"))->dot()->keys()->sort()->values()->all();

    expect($keys('ar'))->toBe($keys('en'));
});
