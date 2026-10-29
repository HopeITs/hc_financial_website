<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateWithFinanceAccount;
use App\Listeners\ApplySavedLocale;
use App\Listeners\LogWebLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Accounts and passwords live in the Windows program's `user` table.
        Fortify::authenticateUsing(new AuthenticateWithFinanceAccount);

        Fortify::loginView(fn () => view('pages.auth.login'));

        Event::listen(Login::class, LogWebLogin::class);
        Event::listen(Login::class, ApplySavedLocale::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
