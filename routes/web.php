<?php

use App\Http\Controllers\SwitchLocaleController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'home' : 'login'));

Route::post('locale', SwitchLocaleController::class)->middleware('throttle:30,1')->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::view('home', 'pages.home')->name('home');

    // Every screen of the navigation. Until a screen is built it shows the placeholder.
    Route::get('screens/{screen}', function (string $screen) {
        abort_unless(in_array($screen, Arr::flatten(config('navigation.groups')), true), 404);

        return view('pages.screen', ['screen' => $screen]);
    })->name('screens.show');
});
