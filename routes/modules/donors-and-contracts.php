<?php

use App\Http\Controllers\ContractFileController;
use App\Livewire\ContractPage;
use App\Livewire\Contracts;
use App\Livewire\Donors;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Funding: donors and agreements (finance design of 2026-11-16)
|--------------------------------------------------------------------------
|
| Module routes load after routes/web.php, so the routes below take the place
| of the placeholder pages that web.php registers for the `donors` and
| `contracts` screens (same address and name as config/navigation.php).
| The `contracts` screen is shown as "Agreements".
|
*/

Route::middleware('auth')->group(function () {
    Route::get('funding/donors', Donors::class)
        ->defaults('screen', 'donors')
        ->middleware('access:donors')
        ->name('funding.donors');

    Route::get('funding/agreements', Contracts::class)
        ->defaults('screen', 'contracts')
        ->middleware('access:contracts')
        ->name('funding.agreements');

    Route::get('funding/agreements/{contract}', ContractPage::class)
        ->whereNumber('contract')
        ->defaults('screen', 'contracts')
        ->middleware('access:contracts')
        ->name('funding.agreements.show');

    Route::get('funding/agreements/{contract}/files/{kind}/{report?}', ContractFileController::class)
        ->whereNumber('contract')
        ->whereIn('kind', ['signed', 'closing', 'report'])
        ->whereNumber('report')
        ->middleware('access:contracts')
        ->name('funding.agreements.file');
});
