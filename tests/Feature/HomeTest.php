<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\Home;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\LineDraft;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    seedChartWithTestProjects();

    $project = fn (string $code, string $name, string $city, ?string $end = null) => GlProject::query()->create([
        'code' => $code, 'name' => $name, 'restriction' => 'With donor restrictions', 'city' => $city, 'end_date' => $end,
        'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false,
    ]);
    $project('EDU', 'Education Homs', 'Homs');
    $project('OLD', 'Ended Empty', 'Homs', '2020-12-31');
    $project('KEEP', 'Ended Holding', 'Homs', '2020-12-31');

    // The cash comes from the approved vouchers: F2F holds dollars and pounds, EDU dollars, KEEP still a little.
    homeCash('F2F', 'USD', '300.10');
    homeCash('F2F', 'SYP', '3000000', '15000');
    homeCash('EDU', 'USD', '500');
    homeCash('KEEP', 'USD', '7');
});

/** An approved receipt of cash for a project. */
function homeCash(string $project, string $currency, string $amount, string $rate = '1'): void
{
    $usd = bcdiv($amount, $rate, 2);
    $cash = $currency === 'SYP' ? '1112' : '1111';

    ledgerApprovedVoucher(lines: [
        new LineDraft($cash, $currency, debit: $amount, rate: $rate, debitUsd: $usd, projectCode: $project, fundSource: FundSource::DonorGrant),
        new LineDraft('4001', $currency, credit: $amount, rate: $rate, creditUsd: $usd, projectCode: $project),
    ]);
}

function homeUser(RoleId $role = RoleId::HeadOffice): User
{
    return User::query()->findOrFail(createFinanceUser('person'.$role->value, 'secret pass', $role->name, $role->value));
}

it('lists the active projects with the cash they hold, and hides ended ones that hold nothing', function () {
    $this->actingAs(homeUser())->get('/home')
        ->assertOk()
        ->assertSee('Family to Family')
        ->assertSee('3,000,000.00')
        ->assertSee('300.10')
        ->assertSee('Education Homs')
        ->assertSee('Ended Holding')
        ->assertDontSee('Ended Empty');
});

it('filters the projects by city', function () {
    Livewire::actingAs(homeUser())->test(Home::class)
        ->assertSee('300.10')->assertSee('500.00')
        ->set('city', 'Homs')
        ->assertSee('500.00')->assertDontSee('300.10');

    // A city without active projects (or unknown) filters nothing.
    Livewire::actingAs(homeUser())->test(Home::class)->set('city', 'Nowhere')->assertSee('300.10')->assertSee('500.00');
});

it('keeps the project balances for a minute, then reads them again', function () {
    $user = homeUser();
    Livewire::actingAs($user)->test(Home::class)->assertSee('500.00');

    homeCash('EDU', 'USD', '200');

    Livewire::actingAs($user)->test(Home::class)->assertSee('500.00')->assertDontSee('700.00');

    $this->travel(61)->seconds();

    Livewire::actingAs($user)->test(Home::class)->assertSee('700.00');
});

it('opens home for every legacy role', function (RoleId $role) {
    $this->actingAs(homeUser($role))->get('/home')->assertOk();
})->with(array_filter(RoleId::cases(), fn (RoleId $role) => ! $role->isWebOnly()));

it('refuses a person who has no role', function () {
    $id = DB::table('user')->insertGetId(['Username' => 'norole', 'Password' => 'x', 'Role' => null]);

    $this->actingAs(User::query()->findOrFail($id))->get('/home')->assertForbidden();
});

it('links the logo to home and keeps the sidebar to the chart of accounts', function () {
    $this->actingAs(homeUser())->get('/home')
        ->assertSee('data-test="home-link"', false)->assertSee(route('home'), false)
        ->assertSee('data-test="chart-tree"', false);
});
