<?php

use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\RecurringPaymentsScreen;
use App\Models\RecurringPayment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\Entries\EntryException;
use App\Services\Entries\RecurringPayments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_25_090000_create_gl_recurring_payments_table.php'))->up();
    (require database_path('migrations/2026_11_25_100000_create_payroll_tables.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-12-01 07:00:00');
});

function rpUser(RoleId $role = RoleId::Accountant, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** @param array<string, string> $changes */
function rpRent(User $by, array $changes = []): RecurringPayment
{
    return app(RecurringPayments::class)->save($by, null, $changes + [
        'budget_line' => 'F2F|Maintenance', 'payee' => 'Abu Fadi', 'description' => 'Office rent', 'currency' => 'USD', 'amount' => '300',
        'every_months' => '1', 'next_due' => '2026-12-01', 'ends_on' => '', 'cash_account' => '1111', 'center' => 'Science Zone Aleppo', 'request' => '',
    ]);
}

it('pays the rent by itself on its day, on its budget line, and moves to the next month', function () {
    $rent = rpRent(rpUser());

    Artisan::call('payments:recurring');

    $voucher = Voucher::query()->sole();
    $lines = VoucherLine::query()->where('voucher_id', $voucher->id)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line) => [$line->account_code, $line->budget_line_key, $line->debit_orig, $line->credit_orig])->all();

    expect($voucher->type)->toBe(VoucherType::Payment)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->date->toDateString())->toBe('2026-12-01')
        ->and($lines)->toBe([['5405', 'F2F|Maintenance', '300.00', '0.00'], ['1111', null, '0.00', '300.00']])
        ->and($rent->refresh()->next_due->toDateString())->toBe('2027-01-01')
        ->and($rent->last_voucher_id)->toBe($voucher->id);

    // Not due again until next month.
    Artisan::call('payments:recurring');
    expect(Voucher::query()->count())->toBe(1);
});

it('stops after the contract ends and keeps the day with the reason when a payment is refused', function () {
    $ann = rpUser();
    $quarterly = rpRent($ann, ['every_months' => '3', 'ends_on' => '2027-01-31']);
    $big = rpRent($ann, ['payee' => 'Big landlord', 'amount' => '900']);

    $result = app(RecurringPayments::class)->payDue(Carbon::today()->toImmutable());

    expect($result)->toBe(['paid' => 1, 'refused' => 1])
        ->and($quarterly->refresh()->active)->toBeFalse()
        ->and($big->refresh()->next_due->toDateString())->toBe('2026-12-01')
        ->and($big->last_problem)->not->toBeNull();
});

it('checks a recurring payment before keeping it', function () {
    expect(fn () => rpRent(rpUser(), ['cash_account' => '1112']))->toThrow(EntryException::class)
        ->and(fn () => rpRent(rpUser(RoleId::Accountant, 'Omar'), ['every_months' => '5']))->toThrow(EntryException::class)
        ->and(RecurringPayment::query()->count())->toBe(0);
});

it('lists the recurring payments and pays a due one at once', function () {
    $ann = rpUser();
    $rent = rpRent($ann);

    Livewire::actingAs($ann)->test(RecurringPaymentsScreen::class)
        ->assertSee('Abu Fadi')
        ->assertSeeHtml('data-test="pay-now-'.$rent->id.'"')
        ->call('payNow', $rent->id)
        ->assertSet('problems', []);

    expect(Voucher::query()->count())->toBe(1);

    $this->actingAs(rpUser(RoleId::Employee, 'Emp'))->get(route('operations.recurring'))->assertForbidden();
});
