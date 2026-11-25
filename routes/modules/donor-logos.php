<?php

use App\Http\Controllers\DonorLogoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donor logos (finance request of 2026-11-28)
|--------------------------------------------------------------------------
|
| A donor's logo, shown on the papers printed for that donor (the delivery
| protocol of a micro project, the donor's financial reports). Set on the
| donors screen.
|
*/

Route::middleware('auth')->group(function () {
    Route::get('funding/donors/{donor}/logo', DonorLogoController::class)
        ->whereNumber('donor')
        ->name('funding.donors.logo');
});
