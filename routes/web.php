<?php

use App\Http\Controllers\RequestQuoteController;
use App\Http\Controllers\SwitchLocaleController;
use App\Http\Controllers\VoucherPrintController;
use App\Livewire\Approval;
use App\Livewire\Chart;
use App\Livewire\FinancialStatements;
use App\Livewire\FollowUpReport;
use App\Livewire\Home;
use App\Livewire\MicroLoans;
use App\Livewire\MultiAttachments;
use App\Livewire\MultiEntry;
use App\Livewire\OperationalReport;
use App\Livewire\Programs;
use App\Livewire\ProjectBalances;
use App\Livewire\ProjectPage;
use App\Livewire\Projects;
use App\Livewire\ReferenceList;
use App\Livewire\Requests;
use App\Livewire\Salaries;
use App\Livewire\SingleEntry;
use App\Livewire\Statement;
use App\Livewire\Transfers;
use App\Livewire\Treasury;
use App\Livewire\TrialBalance;
use App\Livewire\Users;
use App\Livewire\VoucherBuilder;
use App\Livewire\Vouchers;
use App\Services\MasterData\ReferenceData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'home' : 'login'));

Route::post('locale', SwitchLocaleController::class)->middleware('throttle:30,1')->name('locale.update');

// Every signed-in route names its screen: `screen:<slug>` checks config/permissions.php.
Route::middleware('auth')->group(function () {
    Route::get('home', Home::class)->middleware('screen:home')->name('home');

    // A built screen is registered before the placeholder route below; `screen` keeps the menu highlight working.
    Route::get('screens/chart', Chart::class)->defaults('screen', 'chart')->middleware('screen')->name('chart');

    Route::get('screens/programs', Programs::class)->defaults('screen', 'programs')->middleware('screen')->name('programs');

    Route::get('screens/projects', Projects::class)->defaults('screen', 'projects')->middleware('screen')->name('projects');
    Route::get('projects/{account}', ProjectPage::class)->whereNumber('account')->defaults('screen', 'projects')->middleware('screen')->name('projects.show');

    Route::get('screens/statement', Statement::class)->defaults('screen', 'statement')->middleware('screen')->name('statement');

    Route::get('screens/vouchers/new', VoucherBuilder::class)->defaults('screen', 'vouchers')->middleware('screen:vouchers,create')->name('vouchers.create');

    Route::get('screens/vouchers', Vouchers::class)->defaults('screen', 'vouchers')->middleware('screen')->name('vouchers');

    Route::get('screens/single', SingleEntry::class)->defaults('screen', 'single')->middleware('screen')->name('single');

    Route::get('screens/multi', MultiEntry::class)->defaults('screen', 'multi')->middleware('screen')->name('multi');

    Route::get('screens/multiatt', MultiAttachments::class)->defaults('screen', 'multiatt')->middleware('screen')->name('multiatt');

    // The old approval screen is the list of vouchers waiting for approval.
    Route::get('screens/approval', Approval::class)->defaults('screen', 'approval')->middleware('screen')->name('approval');

    Route::get('screens/vouchers/{voucher}/print', VoucherPrintController::class)->defaults('screen', 'vouchers')->middleware('screen')->name('vouchers.print');

    Route::get('screens/requests', Requests::class)->defaults('screen', 'requests')->middleware('screen')->name('requests');
    Route::get('requests/quotes/{quote}', RequestQuoteController::class)->whereNumber('quote')->defaults('screen', 'requests')->middleware('screen')->name('requests.quote');

    Route::get('screens/salaries', Salaries::class)->defaults('screen', 'salaries')->middleware('screen')->name('salaries');

    Route::get('screens/payments', MicroLoans::class)->defaults('screen', 'payments')->middleware('screen')->name('payments');

    Route::get('screens/projbal', ProjectBalances::class)->defaults('screen', 'projbal')->middleware('screen')->name('projbal');

    Route::get('screens/tb', TrialBalance::class)->defaults('screen', 'tb')->middleware('screen')->name('tb');

    Route::get('screens/alloc', Transfers::class)->defaults('screen', 'alloc')->middleware('screen')->name('alloc');

    Route::get('screens/treasury', Treasury::class)->defaults('screen', 'treasury')->middleware('screen')->name('treasury');

    Route::get('screens/statements', FinancialStatements::class)->defaults('screen', 'statements')->middleware('screen')->name('statements');

    foreach (['rexp', 'rcash', 'rbl', 'rrev', 'rfixed', 'rattach'] as $report) {
        Route::get('screens/'.$report, OperationalReport::class)->defaults('screen', $report)->middleware('screen')->name($report);
    }

    foreach (['recon', 'uncomp', 'advances', 'loans'] as $report) {
        Route::get('screens/'.$report, FollowUpReport::class)->defaults('screen', $report)->middleware('screen')->name($report);
    }

    // Master data (task 38): the old program's lists are read-only references; the old project windows are the project page.
    foreach (ReferenceData::SLUGS as $slug) {
        Route::get('screens/'.$slug, ReferenceList::class)->defaults('screen', $slug)->middleware('screen')->name($slug);
    }

    Route::get('screens/users', Users::class)->defaults('screen', 'users')->middleware('screen')->name('users');

    foreach (ReferenceData::MERGED as $slug) {
        Route::get('screens/'.$slug, Projects::class)->defaults('screen', $slug)->middleware('screen')->name($slug);
    }

    // Every screen of the navigation. Until a screen is built it shows the placeholder.
    // An unknown slug is a 404 from routing, before the permission check.
    Route::get('screens/{screen}', fn (string $screen) => view('pages.screen', ['screen' => $screen]))
        ->whereIn('screen', Arr::flatten(config('navigation.groups')))
        ->middleware('screen')
        ->name('screens.show');
});
