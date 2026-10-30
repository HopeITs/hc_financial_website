<?php

use App\Http\Controllers\RequestQuoteController;
use App\Http\Controllers\SwitchLocaleController;
use App\Http\Controllers\VoucherPrintController;
use App\Livewire\Approval;
use App\Livewire\Bridge;
use App\Livewire\Chart;
use App\Livewire\CurrencySettings;
use App\Livewire\FinancialStatements;
use App\Livewire\FollowUpReport;
use App\Livewire\Home;
use App\Livewire\MicroLoans;
use App\Livewire\MonthEndScreen;
use App\Livewire\MultiAttachments;
use App\Livewire\MultiEntry;
use App\Livewire\Notifications;
use App\Livewire\OperationalReport;
use App\Livewire\Operations;
use App\Livewire\Programs;
use App\Livewire\ProjectEditor;
use App\Livewire\ProjectPage;
use App\Livewire\Projects;
use App\Livewire\ReferenceList;
use App\Livewire\Requests;
use App\Livewire\Salaries;
use App\Livewire\Statement;
use App\Livewire\Tills;
use App\Livewire\Transfers;
use App\Livewire\Treasury;
use App\Livewire\TrialBalance;
use App\Livewire\Users;
use App\Livewire\VoucherBuilder;
use App\Livewire\Vouchers;
use App\Services\MasterData\ReferenceData;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'home' : 'login'));

Route::post('locale', SwitchLocaleController::class)->middleware('throttle:30,1')->name('locale.update');

// Every signed-in route names its screen: `screen:<slug>` checks config/permissions.php.
Route::middleware('auth')->group(function () {
    // The home page and the reports open with a skeleton and load their figures right after (U-10).
    $defer = (bool) config('finance.defer_reports');

    Route::get('home', Home::class)->middleware('screen:home')->name('home')->defer($defer);
    // Everyone who can sign in sees their own notifications, so the page follows the home screen's permission.
    Route::get('notifications', Notifications::class)->middleware('screen:home')->name('notifications');

    // A screen's address and route name come from its path in config/navigation.php; the `screen`
    // default is its permission slug and keeps the menu highlight working.
    $built = [];
    $screen = function (string $slug, string $component) use (&$built) {
        $built[] = $slug;

        return Route::get(Navigation::path($slug), $component)
            ->defaults('screen', $slug)
            ->middleware('screen')
            ->name(Navigation::routeName($slug));
    };

    $screen('chart', Chart::class);

    $screen('programs', Programs::class);
    $screen('projects', Projects::class);
    Route::get('projects/new', ProjectEditor::class)->defaults('screen', 'projects')->middleware('screen:projects,create')->name('projects.new');
    Route::get('projects/{code}/edit', ProjectEditor::class)->where('code', '[A-Za-z0-9_-]+')->defaults('screen', 'projects')->middleware('screen:projects,edit')->name('projects.edit');
    Route::get('projects/{account}', ProjectPage::class)->whereNumber('account')->defaults('screen', 'projects')->middleware('screen')->name('projects.show');

    // Master data (task 38): the old project windows are the project page.
    foreach (ReferenceData::MERGED as $slug) {
        $screen($slug, Projects::class);
    }

    // Registered before the vouchers list so `new` is not read as a list filter.
    Route::get('entries/vouchers/new', VoucherBuilder::class)->defaults('screen', 'vouchers')->middleware('screen:vouchers,create')->name('entries.vouchers.create');
    Route::get('entries/vouchers/{voucher}/print', VoucherPrintController::class)->defaults('screen', 'vouchers')->middleware('screen')->name('entries.vouchers.print');
    $screen('vouchers', Vouchers::class);

    // Operations (F-02) took the place of Single entry: same permission key, new address; the old one redirects.
    $screen('single', Operations::class);
    Route::get('entries/single', fn (Request $request) => redirect()->route(Navigation::routeName('single'), $request->query(), 301))->middleware('screen:single');
    $screen('multi', MultiEntry::class);
    $screen('multiatt', MultiAttachments::class);
    $screen('payments', MicroLoans::class);

    // The old approval screen is the list of vouchers waiting for approval.
    $screen('approval', Approval::class);

    $screen('requests', Requests::class);
    Route::get('entries/requests/quotes/{quote}', RequestQuoteController::class)->whereNumber('quote')->defaults('screen', 'requests')->middleware('screen')->name('entries.requests.quote');

    $screen('treasury', Treasury::class);
    $screen('tills', Tills::class);
    $screen('monthend', MonthEndScreen::class);
    $screen('alloc', Transfers::class);

    foreach (['rexp', 'rcash', 'rbl', 'rrev', 'rfixed', 'rattach'] as $report) {
        $screen($report, OperationalReport::class)->defer($defer);
    }

    $screen('tb', TrialBalance::class)->defer($defer);
    $screen('statement', Statement::class)->defer($defer);
    $screen('statements', FinancialStatements::class)->defer($defer);

    foreach (['recon', 'mapprovals', 'uncomp', 'xreserve', 'advances', 'loans'] as $report) {
        $screen($report, FollowUpReport::class)->defer($defer);
    }

    $screen('salaries', Salaries::class);
    $screen('users', Users::class);

    // Bridge pages (task 39): read-only, for Head Office and the Financial Manager.
    foreach (Bridge::PAGES as $page) {
        $screen($page, Bridge::class);
    }

    // The currencies are the ledger's own table (R4-4), edited here; the other references are read-only.
    $screen('currency', CurrencySettings::class);

    // Master data (task 38): the old program's lists are read-only references.
    foreach (array_diff(ReferenceData::SLUGS, ['currency', 'tills']) as $slug) {
        $screen($slug, ReferenceList::class);
    }

    // Every other screen of the navigation shows the placeholder until it is built.
    foreach (array_diff(Navigation::slugs(), $built) as $slug) {
        Route::view(Navigation::path($slug), 'pages.screen', ['screen' => $slug])
            ->defaults('screen', $slug)
            ->middleware('screen')
            ->name(Navigation::routeName($slug));
    }

    // The old `screens/<slug>` addresses (bookmarks, links in old messages) move to the readable paths.
    // An unknown slug is a 404 from routing, before the permission check.
    Route::get('screens/vouchers/new', fn () => redirect()->route('entries.vouchers.create', status: 301))->middleware('screen:vouchers,create');
    Route::get('screens/vouchers/{voucher}/print', fn (string $voucher) => redirect()->route('entries.vouchers.print', ['voucher' => $voucher], 301))->middleware('screen:vouchers');
    Route::get('screens/{screen}', fn (Request $request, string $screen) => redirect()->route(Navigation::routeName($screen), $request->query(), 301))
        ->whereIn('screen', Navigation::slugs())
        ->middleware('screen');
});

// An unknown address still runs the web middleware, so its 404 page is in the person's chosen language (U-02).
Route::fallback(fn () => abort(404));
