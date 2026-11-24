<?php

use App\Http\Controllers\MicroFileDocumentController;
use App\Http\Controllers\MicroFilePrintController;
use App\Http\Controllers\PayrollPrintController;
use App\Http\Controllers\PurchasePrintController;
use App\Http\Controllers\PurchaseQuoteController;
use App\Http\Controllers\RequestQuoteController;
use App\Http\Controllers\SwitchLocaleController;
use App\Http\Controllers\VoucherPrintController;
use App\Livewire\Approval;
use App\Livewire\ApprovalsInboxScreen;
use App\Livewire\AuditLog;
use App\Livewire\Chart;
use App\Livewire\CurrencySettings;
use App\Livewire\DailyCashCount;
use App\Livewire\DonorReportScreen;
use App\Livewire\ExchangeRates;
use App\Livewire\FinancialStatements;
use App\Livewire\FollowUpReport;
use App\Livewire\Home;
use App\Livewire\LiquidityPosition;
use App\Livewire\InstalmentFundsScreen;
use App\Livewire\MicroProjectFiles;
use App\Livewire\MicroProjectsHome;
use App\Livewire\MicroLoans;
use App\Livewire\MonthEndScreen;
use App\Livewire\Notifications;
use App\Livewire\OpeningBalances;
use App\Livewire\OperationalReport;
use App\Livewire\OperationsHome;
use App\Livewire\PaymentVoucher;
use App\Livewire\PayrollScreen;
use App\Livewire\ProjectRevenuesScreen;
use App\Livewire\Purchases;
use App\Livewire\ReceiptVoucher;
use App\Livewire\RecurringPaymentsScreen;
use App\Livewire\ReferenceList;
use App\Livewire\ReportsHome;
use App\Livewire\Requests;
use App\Livewire\Salaries;
use App\Livewire\StaffAdvances;
use App\Livewire\Statement;
use App\Livewire\TreasuryTransfers;
use App\Livewire\TrialBalance;
use App\Livewire\Users;
use App\Livewire\VoucherBuilder;
use App\Livewire\VoucherImportScreen;
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

    // Registered before the vouchers list so `new` is not read as a list filter.
    Route::get('entries/vouchers/new', VoucherBuilder::class)->defaults('screen', 'vouchers')->middleware('screen:vouchers,create')->name('entries.vouchers.create');
    Route::get('entries/vouchers/{voucher}/edit', VoucherBuilder::class)->defaults('screen', 'vouchers')->middleware('screen:vouchers,create')->name('entries.vouchers.edit');
    Route::get('entries/vouchers/{voucher}/print', VoucherPrintController::class)->defaults('screen', 'vouchers')->middleware('screen')->name('entries.vouchers.print');
    $screen('vouchers', Vouchers::class);

    // The payment and receipt vouchers (finance design of 2026-11-19) take the place of Operations: the payment keeps the
    // `single` permission key, and the old addresses lead to it.
    $screen('single', PaymentVoucher::class);
    $screen('receipt', ReceiptVoucher::class);
    // The journal entry is the voucher builder opened on a journal voucher; the advances are given and settled here.
    $screen('journal', VoucherBuilder::class)->middleware('screen:vouchers,create');
    $screen('advance', StaffAdvances::class);
    Route::get('entries/single', fn () => redirect()->route(Navigation::routeName('single'), status: 301))->middleware('screen:single');
    Route::get('entries/operations', fn () => redirect()->route(Navigation::routeName('single'), status: 301))->middleware('screen:single');
    // Vouchers from Excel (finance decision of 2026-11-20) take the place of the old multi entry.
    $screen('multi', VoucherImportScreen::class);
    // Micro-project files (finance decisions of 2026-11-23): details, quotes, payment order, purchases, receipt protocol.
    $screen('microhome', MicroProjectsHome::class);
    $screen('microfiles', MicroProjectFiles::class);
    Route::get('entries/micro-files/{file}/print/{form}', MicroFilePrintController::class)->whereNumber('file')->defaults('screen', 'microfiles')->middleware('screen')->name('entries.micro-files.print');
    Route::get('entries/micro-files/documents/{kind}/{id}', MicroFileDocumentController::class)->whereIn('kind', ['quote', 'invoice'])->whereNumber('id')->defaults('screen', 'microfiles')->middleware('screen')->name('entries.micro-files.document');
    $screen('payments', MicroLoans::class);
    $screen('loanfunds', InstalmentFundsScreen::class);

    // The old approval screen is the list of vouchers waiting for approval.
    $screen('approval', Approval::class);

    // The financial operations in one place (finance request of 2026-11-24).
    $screen('operations', OperationsHome::class);
    // Everything waiting for one's approval (finance decision of 2026-11-25).
    $screen('inbox', ApprovalsInboxScreen::class);
    // What the projects earn themselves: a day's takings and the price lists (finance request of 2026-11-24).
    $screen('revenues', ProjectRevenuesScreen::class);
    // Rent and the other recurring payments (finance decision of 2026-11-24).
    $screen('recurring', RecurringPaymentsScreen::class);
    // The monthly payroll of each project, its summary and payslips (finance request of 2026-11-24).
    $screen('payroll', PayrollScreen::class);
    Route::get('operations/payroll/{payroll}/print/{form}', PayrollPrintController::class)->whereNumber('payroll')->whereIn('form', PayrollPrintController::FORMS)->defaults('screen', 'payroll')->middleware('screen')->name('operations.payroll.print');
    $screen('requests', Requests::class);
    // Purchase files (finance request of 2026-11-24): the procurement cycle of an approved request, and its forms.
    $screen('purchases', Purchases::class);
    Route::get('entries/purchases/{purchase}/print/{form}', PurchasePrintController::class)->whereNumber('purchase')->defaults('screen', 'purchases')->middleware('screen')->name('entries.purchases.print');
    Route::get('entries/purchases/quotes/{quote}', PurchaseQuoteController::class)->whereNumber('quote')->defaults('screen', 'purchases')->middleware('screen')->name('entries.purchases.quote');
    Route::get('entries/requests/quotes/{quote}', RequestQuoteController::class)->whereNumber('quote')->defaults('screen', 'requests')->middleware('screen')->name('entries.requests.quote');

    // One treasury (finance decision of 2026-11-20): the liquidity position and the daily cash count take the place of
    // the SYP balances by general account and the tills by project owner.
    $screen('treasury', LiquidityPosition::class);
    $screen('tills', DailyCashCount::class);
    $screen('monthend', MonthEndScreen::class);
    $screen('opening', OpeningBalances::class);
    // One treasury (finance decision of 2026-11-19): the daily rates take the place of the allocation rates per city.
    $screen('alloc', ExchangeRates::class);
    $screen('transfers', TreasuryTransfers::class);

    // Every report in one page, in three parts (finance decision of 2026-11-25).
    $screen('reports', ReportsHome::class);

    foreach (['rexp', 'rcash', 'rrev', 'rfixed', 'rattach'] as $report) {
        $screen($report, OperationalReport::class)->defer($defer);
    }

    $screen('tb', TrialBalance::class)->defer($defer);
    $screen('statement', Statement::class)->defer($defer);
    $screen('statements', FinancialStatements::class)->defer($defer);
    $screen('rdonor', DonorReportScreen::class)->defer($defer);

    foreach (['recon', 'mapprovals', 'uncomp', 'xreserve', 'advances', 'loans'] as $report) {
        $screen($report, FollowUpReport::class)->defer($defer);
    }

    $screen('salaries', Salaries::class);
    $screen('users', Users::class);
    $screen('auditlog', AuditLog::class);

    // The currencies are the ledger's own table (R4-4), edited here; the other references are read-only.
    $screen('currency', CurrencySettings::class);

    // Master data (task 38): the old program's lists are read-only references.
    // Fiscal bonds left the menu with the old entry screens (finance decision of 2026-11-19).
    foreach (array_diff(ReferenceData::SLUGS, ['currency', 'tills', 'bonds']) as $slug) {
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
