<?php

use App\Http\Controllers\FixedAssetPrintController;
use App\Livewire\FixedAssetsScreen;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Warehouses and fixed assets (finance request of 2026-11-28)
|--------------------------------------------------------------------------
|
| The register, its warehouses and kinds, the counts and the month's
| depreciation, and the papers: an asset's handover note and a count sheet.
| This page takes the place of the menu's placeholder for `assets`.
|
*/

Route::middleware('auth')->group(function () {
    Route::get('operations/assets', FixedAssetsScreen::class)
        ->defaults('screen', 'assets')
        ->middleware('access:assets')
        ->name('operations.assets');

    Route::get('operations/assets/movements/{movement}/print', [FixedAssetPrintController::class, 'movement'])
        ->whereNumber('movement')
        ->defaults('screen', 'assets')
        ->middleware('access:assets')
        ->name('operations.assets.movement');

    Route::get('operations/assets/counts/{count}/print', [FixedAssetPrintController::class, 'count'])
        ->whereNumber('count')
        ->defaults('screen', 'assets')
        ->middleware('access:assets')
        ->name('operations.assets.count');
});
