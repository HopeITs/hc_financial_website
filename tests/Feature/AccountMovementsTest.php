<?php

use App\Enums\RoleId;
use App\Livewire\Chart;
use App\Models\GlAccount;
use App\Models\GlCity;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    $this->seed(ChartOfAccountsSeeder::class);
});

function movementsUser(): User
{
    return User::query()->findOrFail(createFinanceUser('mover', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
}

function postMovement(string $id, string $date, string $cash, string $other, string $amount, string $status = 'approved'): void
{
    Voucher::query()->create(['id' => $id, 'type' => 'CRV', 'date' => $date, 'status' => $status, 'prepared_by' => 5, 'description' => 'Test '.$id]);
    VoucherLine::query()->create(['voucher_id' => $id, 'line_no' => 1, 'account_code' => $cash, 'currency' => 'USD', 'debit_orig' => $amount, 'debit_usd' => $amount]);
    VoucherLine::query()->create(['voucher_id' => $id, 'line_no' => 2, 'account_code' => $other, 'currency' => 'USD', 'credit_orig' => $amount, 'credit_usd' => $amount]);
}

it('lists the movements of an account with its counter accounts, inside a period or all of them', function () {
    $counter = GlAccount::query()->where('level', 'posting')->where('class', 'income')->firstOrFail();
    postMovement('CRV-2026-0001', '2026-03-01', '1111', $counter->code, '100.00');
    postMovement('CRV-2026-0002', '2026-05-01', '1111', $counter->code, '40.00');
    postMovement('CRV-2026-0003', '2026-05-02', '1111', $counter->code, '9.00', 'pending_approval');

    $page = Livewire::actingAs(movementsUser())->test(Chart::class, ['code' => '1111'])
        ->set('movAll', false)->set('movFrom', '2026-04-01')->set('movTo', '2026-06-01');

    $page->assertSee('CRV-2026-0002')->assertDontSee('CRV-2026-0001')->assertDontSee('CRV-2026-0003')
        ->assertSee($counter->code.' · ');

    $page->set('movAll', true)->assertSee('CRV-2026-0001')->assertSee('CRV-2026-0002')->assertSeeHtml('movements-total-USD');
});

it('adds, renames and deactivates a city, and follows a rename to the accounts', function () {
    GlAccount::query()->where('code', '1111')->update(['city' => 'Idlib']);
    GlCity::query()->create(['name' => 'Idlib']);

    Livewire::actingAs(movementsUser())->test(Chart::class)
        ->call('openCities')
        ->set('cityName', 'Idlib')->call('saveCity')->assertHasErrors('cityName');

    $component = Livewire::actingAs(movementsUser())->test(Chart::class)
        ->call('openCities')->set('cityName', 'Raqqa')->call('saveCity')->assertHasNoErrors();

    $city = GlCity::query()->where('name', 'Raqqa')->firstOrFail();
    $idlib = GlCity::query()->where('name', 'Idlib')->firstOrFail();

    $component->call('editCity', $idlib->id)->set('cityName', 'Idleb')->call('saveCity')->assertHasNoErrors();
    expect(GlAccount::query()->find('1111')->city)->toBe('Idleb');

    $component->call('toggleCity', $city->id);
    expect($city->fresh()->is_active)->toBeFalse();
});
