<?php

use App\Http\Controllers\SwitchLocaleController;
use App\Livewire\Home;
use App\Livewire\Programs;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'home' : 'login'));

Route::post('locale', SwitchLocaleController::class)->middleware('throttle:30,1')->name('locale.update');

// Every signed-in route names its screen: `screen:<slug>` checks config/permissions.php.
Route::middleware('auth')->group(function () {
    Route::get('home', Home::class)->middleware('screen:home')->name('home');

    // A built screen is registered before the placeholder route below; `screen` keeps the menu highlight working.
    Route::get('screens/programs', Programs::class)->defaults('screen', 'programs')->middleware('screen')->name('programs');

    // Every screen of the navigation. Until a screen is built it shows the placeholder.
    // An unknown slug is a 404 from routing, before the permission check.
    Route::get('screens/{screen}', fn (string $screen) => view('pages.screen', ['screen' => $screen]))
        ->whereIn('screen', Arr::flatten(config('navigation.groups')))
        ->middleware('screen')
        ->name('screens.show');
});
