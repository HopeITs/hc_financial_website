<?php

use App\Http\Controllers\ContractFileController;
use App\Livewire\ContractPage;
use App\Livewire\Contracts;
use App\Livewire\Donors;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donors and funding contracts (finance design of 2026-11-16)
|--------------------------------------------------------------------------
|
| Module routes load after routes/web.php, so the Donors route below takes the
| place of the placeholder page that web.php registers for the `donors` screen
| (same address and name). The contracts screen `contracts` still has to be
| added to config/navigation.php and config/permissions.php by IT; until then
| only the super user passes `access:contracts`.
|
*/

Route::middleware('auth')->group(function () {
    Route::get('people/donors', Donors::class)
        ->defaults('screen', 'donors')
        ->middleware('access:donors')
        ->name('people.donors');

    Route::get('projects/contracts', Contracts::class)
        ->defaults('screen', 'contracts')
        ->middleware('access:contracts')
        ->name('projects.contracts');

    Route::get('projects/contracts/{contract}', ContractPage::class)
        ->whereNumber('contract')
        ->defaults('screen', 'contracts')
        ->middleware('access:contracts')
        ->name('projects.contracts.show');

    Route::get('projects/contracts/{contract}/files/{kind}/{report?}', ContractFileController::class)
        ->whereNumber('contract')
        ->whereIn('kind', ['signed', 'closing', 'report'])
        ->whereNumber('report')
        ->middleware('access:contracts')
        ->name('projects.contracts.file');
});
