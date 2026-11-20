<?php

use App\Enums\RoleId;
use App\Livewire\Home;
use App\Livewire\HomeOverview;
use App\Models\Contract;
use App\Models\ContractInstalment;
use App\Models\ContractProject;
use App\Models\Donor;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Home\HomeOverview as Overview;
use App\Services\Home\WaitingWork;
use App\Services\Ledger\LineDraft;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\MonthlyRates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-16 10:00:00');
});

function hdUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** 100 $ in the Aleppo dollar safe, and 1,350,000 SYP in its pound safe booked at 13,000 (103.85 $), both in October. */
function hdMoney(): void
{
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-10-05')]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-10-10')], [
        new LineDraft('1112', 'SYP', debit: 1350000, rate: 13000, debitUsd: 103.85),
        new LineDraft('4001', 'SYP', credit: 1350000, rate: 13000, creditUsd: 103.85, projectCode: 'F2F'),
    ]);
}

/** A running euro agreement: instalment 1 late, 2 waiting for its condition, 3 due in 15 days; it ends in 45 days. */
function hdAgreement(): Contract
{
    $donor = Donor::query()->create([
        'code' => 'CARPL', 'name_en' => 'Caritas Poland', 'name_ar' => 'كاريتاس بولندا', 'type' => 'church_agency',
        'country' => 'Poland', 'currency' => 'EUR', 'is_active' => true,
    ]);
    $contract = Contract::query()->create([
        'number' => 'CON-2026-001', 'donor_id' => $donor->id, 'title' => 'Education 2026', 'signed_on' => '2026-03-01', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31',
        'currency' => 'EUR', 'amount' => '11000', 'fixed_rate' => '1.15', 'restriction' => 'restricted', 'status' => 'running', 'created_by' => 1,
        'approved_by' => 1, 'approved_at' => '2026-03-01 09:00:00',
    ]);

    ContractProject::query()->create(['contract_id' => $contract->id, 'project_code' => 'F2F', 'amount' => '11000']);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 1, 'due_on' => '2026-03-01', 'amount' => '6000', 'is_conditional' => false]);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 2, 'due_on' => '2026-09-01', 'amount' => '4000', 'condition' => 'After the interim report', 'is_conditional' => true]);
    ContractInstalment::query()->create(['contract_id' => $contract->id, 'number' => 3, 'due_on' => '2026-12-01', 'amount' => '1000', 'is_conditional' => false]);

    return $contract;
}

/** @return array<string, int> key => count */
function hdWaiting(User $user): array
{
    return collect(app(WaitingWork::class)->for($user))->mapWithKeys(fn (array $item) => [$item['key'] => $item['count']])->all();
}

it('gives each role the quick actions it may do, and none to a role that enters nothing', function () {
    Livewire::actingAs(hdUser(RoleId::Accountant, 'Ann'))->test(Home::class)
        ->assertSeeHtml('data-test="home-quick"')
        ->assertSeeHtml('data-test="quick-single"')
        ->assertSeeHtml('data-test="quick-journal"')
        ->assertSeeHtml('data-test="quick-tills"');

    Livewire::actingAs(hdUser(RoleId::Cashier, 'Carl'))->test(Home::class)
        ->assertSeeHtml('data-test="quick-receipt"')
        ->assertDontSeeHtml('data-test="quick-journal"');

    Livewire::actingAs(hdUser(RoleId::Employee, 'Emp'))->test(Home::class)
        ->assertDontSeeHtml('data-test="home-quick"');
});

it('adds the treasury routine to what waits: rates to approve, today\'s counts, last month\'s closing and revaluation', function () {
    hdMoney();
    $accountant = hdUser(RoleId::Accountant, 'Ann');
    $manager = hdUser(RoleId::FinancialManager, 'Fadi');
    app(MonthlyRates::class)->set('2026-10', 'SYP', '13500', null, $manager);
    app(DailyRates::class)->propose('EUR', '2026-11-16', '1.17', null, $accountant, approveNow: false);

    // The manager approves rates and closes months; the accountant counts the cash and prepares the revaluation.
    expect(hdWaiting($manager))->toMatchArray(['rates' => 1, 'month_close' => 1])
        ->and(hdWaiting($manager))->not->toHaveKey('counts')
        ->and(hdWaiting($accountant))->toMatchArray(['counts' => 2, 'revaluation' => 1])
        ->and(hdWaiting($accountant))->not->toHaveKey('rates');
});

it('shows today\'s rates, flagging a rate that is not today\'s and a provisional monthly rate', function () {
    $manager = hdUser(RoleId::FinancialManager, 'Fadi');
    app(DailyRates::class)->propose('EUR', '2026-11-15', '1.16', null, $manager, approveNow: true);
    app(MonthlyRates::class)->set('2026-10', 'SYP', '13500', null, $manager);

    $rates = collect(app(Overview::class)->rates(CarbonImmutable::today()))->keyBy('currency');

    expect($rates['EUR'])->toMatchArray(['rate' => '1.16', 'kind' => 'daily', 'as_of' => '15/11/2026', 'stale' => true])
        ->and($rates['SYP'])->toMatchArray(['rate' => '13500', 'kind' => 'monthly', 'as_of' => '10/2026', 'provisional' => true]);
});

it('shows the liquidity today at today\'s value', function () {
    hdMoney();
    app(MonthlyRates::class)->set('2026-11', 'SYP', '13500', null, hdUser(RoleId::FinancialManager, 'Fadi'));

    // 100 $ and 1,350,000 SYP at this month's 13,500: 200 $ in Aleppo.
    expect(app(Overview::class)->liquidity(CarbonImmutable::today()))->toMatchArray(['total_value' => '200.00', 'missing_rates' => []]);
});

it('lists the late instalment first, then those coming, and the agreements ending soon', function () {
    hdAgreement();
    $funding = app(Overview::class)->instalments(hdUser(RoleId::FinancialManager, 'Fadi'), CarbonImmutable::today());

    expect(array_map(fn (array $row): array => [$row['instalment'], $row['late'], $row['conditional'], $row['remaining']], $funding['instalments']))->toBe([
        [1, true, false, '6000.00'],
        [2, false, true, '4000.00'],
        [3, false, false, '1000.00'],
    ])
        ->and($funding['late_count'])->toBe(1)
        ->and($funding['instalments'][2]['days'])->toBe(15)
        ->and(array_column($funding['ending'], 'number'))->toBe(['CON-2026-001']);
});

it('puts the overview on the home page only with the parts the role sees', function () {
    hdMoney();
    hdAgreement();

    Livewire::actingAs(hdUser(RoleId::Accountant, 'Ann'))->test(Home::class)->assertSeeHtml('data-test="home-overview-loading"');

    Livewire::withoutLazyLoading()->actingAs(hdUser(RoleId::FinancialManager, 'Fadi'))->test(HomeOverview::class)
        ->assertSeeHtml('data-test="home-liquidity"')
        ->assertSeeHtml('data-test="home-rates"')
        ->assertSeeHtml('data-test="home-funding"')
        ->assertSeeHtml('data-test="instalment-late"')
        ->assertSee('CON-2026-001');

    Livewire::withoutLazyLoading()->actingAs(hdUser(RoleId::Employee, 'Emp'))->test(HomeOverview::class)
        ->assertDontSeeHtml('data-test="home-liquidity"')
        ->assertDontSeeHtml('data-test="home-rates"')
        ->assertDontSeeHtml('data-test="home-funding"');
});
