<?php

use App\Livewire\FinancialStatements;
use App\Livewire\FollowUpReport;
use App\Livewire\Home;
use App\Livewire\OperationalReport;
use App\Livewire\Statement;
use App\Livewire\TrialBalance;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
    createLegacyHomeSchema();
    $this->deferUser = User::query()->findOrFail(createFinanceUser('Ho', 'secret pass'));
});

/** @return array{0: string, 1: array<string, string>} */
function deferredScreen(string $component): array
{
    return match ($component) {
        OperationalReport::class => [$component, ['screen' => 'rexp']],
        FollowUpReport::class => [$component, ['screen' => 'advances']],
        default => [$component, []],
    };
}

it('opens the home page and every report with a skeleton when deferred, then loads the screen', function (string $component) {
    [$class, $params] = deferredScreen($component);

    $page = Livewire::actingAs($this->deferUser)->test($class, $params + ['defer' => true])
        ->assertSeeHtml('data-test="report-skeleton"')
        ->assertSee(__('common.loading_report'))
        ->assertDontSeeHtml('data-test="report-loading"');

    preg_match("/__lazyLoad\\((?:'|&#039;)([A-Za-z0-9+\\/=]+)/", $page->html(), $match);
    expect($match)->toHaveKey(1);

    $page->call('__lazyLoad', $match[1])
        ->assertDontSeeHtml('data-test="report-skeleton"')
        ->assertSeeHtml('data-test="report-loading"');
})->with([Home::class, TrialBalance::class, Statement::class, FinancialStatements::class, OperationalReport::class, FollowUpReport::class]);

it('keeps the filters in the address when the deferred screen loads', function () {
    $page = Livewire::withQueryParams(['asAt' => '2026-01-31', 'currency' => 'USD'])->actingAs($this->deferUser)
        ->test(TrialBalance::class, ['defer' => true]);

    preg_match("/__lazyLoad\\((?:'|&#039;)([A-Za-z0-9+\\/=]+)/", $page->html(), $match);

    $page->call('__lazyLoad', $match[1])->assertSet('asAt', '2026-01-31');
});

it('titles the skeleton with the screen it stands for', function () {
    Livewire::actingAs($this->deferUser)->test(OperationalReport::class, ['screen' => 'rexp', 'defer' => true])
        ->assertSee(__('navigation.screens.rexp'));

    Livewire::actingAs($this->deferUser)->test(Home::class, ['defer' => true])->assertSee(__('home.title'));
});

it('defers exactly the home page and the reports, following the setting', function () {
    $deferred = collect(app('router')->getRoutes())->filter(fn ($route) => array_key_exists('defer', $route->defaults))
        ->map(fn ($route) => $route->getName())->sort()->values()->all();

    $expected = collect(['tb', 'statement', 'statements', 'rexp', 'rcash', 'rbl', 'rrev', 'rfixed', 'rattach', 'recon', 'mapprovals', 'uncomp', 'xreserve', 'advances', 'loans'])
        ->map(fn (string $slug) => Navigation::routeName($slug))->push('home')->sort()->values()->all();

    expect($deferred)->toBe($expected)
        ->and(collect(app('router')->getRoutes())->filter(fn ($route) => array_key_exists('defer', $route->defaults))->pluck('defaults.defer')->unique()->all())
        ->toBe([config('finance.defer_reports')]);
});

it('has the loading texts in English and Arabic', function () {
    foreach (['loading', 'loading_report'] as $key) {
        expect(trans('common.'.$key, [], 'en'))->not->toBe('common.'.$key)
            ->and(trans('common.'.$key, [], 'ar'))->not->toBe('common.'.$key)
            ->and(trans('common.'.$key, [], 'ar'))->not->toBe(trans('common.'.$key, [], 'en'));
    }
});
