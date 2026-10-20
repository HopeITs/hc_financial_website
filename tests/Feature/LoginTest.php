<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();
    createFinanceUser('Mai', 'correct horse');
});

it('sends guests to the sign-in page', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get('/home')->assertRedirect(route('login'));
    $this->get('/screens/chart')->assertRedirect(route('login'));
});

it('shows the sign-in form with a username and a password', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('name="username"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('two-factor');
});

it('signs in with the account of the Windows program and logs it', function () {
    $this->post('/login', ['username' => 'Mai', 'password' => 'correct horse'])
        ->assertRedirect('/home');

    $this->assertAuthenticated();

    expect(DB::table('log')->where('title', 'LOGIN')->where('user', 'Mai')->exists())->toBeTrue();

    $this->get('/home')->assertOk()->assertSee('Mai')->assertSee('Financial manager');
});

it('refuses a wrong password, an unknown user and a different letter case', function (string $username, string $password) {
    $this->post('/login', ['username' => $username, 'password' => $password])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
})->with([
    'wrong password' => ['Mai', 'wrong'],
    'unknown user' => ['Nobody', 'correct horse'],
    'other letter case' => ['mai', 'correct horse'],
]);

it('writes nothing to the user table when signing in', function () {
    $before = DB::table('user')->first();

    $this->post('/login', ['username' => 'Mai', 'password' => 'correct horse']);

    expect(DB::table('user')->first())->toEqual($before)
        ->and(User::query()->first()->getRememberTokenName())->toBe('');
});

it('opens every screen of the navigation for a signed-in user', function () {
    $this->actingAs(User::query()->firstOrFail());

    foreach (collect(config('navigation.groups'))->flatten() as $screen) {
        $this->get(route('screens.show', $screen))->assertOk();
    }

    $this->get('/screens/unknown')->assertNotFound();
});

it('switches the interface to Arabic with a right to left page', function () {
    $this->from('/login')->post('/locale', ['locale' => 'ar'])->assertRedirect('/login');

    $this->get('/login')->assertSee('dir="rtl"', false)->assertSee('اسم المستخدم');
});

it('signs out', function () {
    $this->post('/login', ['username' => 'Mai', 'password' => 'correct horse']);

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

it('sends browser hardening headers', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeaderMissing('X-Powered-By');
});
