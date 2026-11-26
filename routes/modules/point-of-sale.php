<?php

use App\Http\Controllers\PointOfSalePrintController;
use App\Livewire\PointOfSale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Point of sale of the project revenues (finance request of 2026-11-27)
|--------------------------------------------------------------------------
|
| The full-screen point of sale (tables, orders, the add-items window), an
| order's receipt and the shift's end-of-day report, for those who may see
| the revenues screen.
|
*/

Route::middleware('auth')->group(function () {
    Route::get('operations/revenues/pos', PointOfSale::class)
        ->middleware('access:revenues')
        ->name('operations.revenues.pos');

    Route::get('operations/revenues/pos/orders/{order}/receipt', [PointOfSalePrintController::class, 'receipt'])
        ->whereNumber('order')
        ->middleware('access:revenues')
        ->name('operations.revenues.pos.receipt');

    Route::get('operations/revenues/pos/shifts/{shift}/report', [PointOfSalePrintController::class, 'report'])
        ->whereNumber('shift')
        ->middleware('access:revenues')
        ->name('operations.revenues.pos.report');
});
