<?php

use App\Enums\RoleId;
use App\Livewire\MyTill;
use App\Models\User;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherApprovals;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-26 10:00:00');
});

function tillUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

it('shows the cashier their city\'s accounts, today\'s moves and their own vouchers, a rejected one with its reason', function () {
    $cashier = tillUser(RoleId::Cashier, 'Carl');
    $manager = tillUser(RoleId::FinancialManager, 'Fatima');
    $today = CarbonImmutable::today();
    $kept = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $cashier->getKey(), 'date' => $today, 'description' => 'Bank interest']));
    $rejected = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $cashier->getKey(), 'date' => $today, 'description' => 'Wrong amount']));
    app(VoucherApprovals::class)->reject($rejected, $manager, 'No receipt attached');

    Livewire::actingAs($cashier)->test(MyTill::class)
        ->set('city', '')
        ->assertSeeHtml('data-test="till-account-1111"')
        ->assertSee('Bank interest')
        ->assertSeeHtml('data-test="mine-'.$kept->id.'"')
        ->assertSeeHtml('data-test="mine-'.$rejected->id.'"')
        ->assertSee(__('my_till.rejected_because', ['reason' => 'No receipt attached']))
        ->assertSeeHtml('data-test="till-action-tills"')
        ->assertSeeHtml('data-test="till-action-payments"');

    expect(Navigation::visibleGroups($cashier)['treasury'])->toContain('mytill');
});

it('opens for the cashier and the accountant, not for the others', function () {
    $this->actingAs(tillUser(RoleId::Cashier, 'Carl'))->get(route(Navigation::routeName('mytill')))->assertOk();
    $this->actingAs(tillUser(RoleId::Accountant, 'Ann'))->get(route(Navigation::routeName('mytill')))->assertOk();
    $this->actingAs(tillUser(RoleId::ProjectManager, 'Pam'))->get(route(Navigation::routeName('mytill')))->assertForbidden();
});
