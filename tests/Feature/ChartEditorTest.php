<?php

use App\Enums\RoleId;
use App\Livewire\Chart;
use App\Models\GlAccount;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\Chart\AccountBalances;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    $this->seed(ChartOfAccountsSeeder::class);
});

function editorUser(RoleId $role = RoleId::HeadOffice): User
{
    return User::query()->findOrFail(createFinanceUser('editor'.$role->value, 'secret pass', $role->name, $role->value));
}

function useAccount(string $code = '1111', string $status = 'approved'): void
{
    Voucher::query()->create(['id' => 'CRV-2026-0001', 'type' => 'CRV', 'date' => '2026-03-01', 'status' => $status, 'prepared_by' => 5]);
    VoucherLine::query()->create([
        'voucher_id' => 'CRV-2026-0001', 'line_no' => 1, 'account_code' => $code, 'currency' => 'USD',
        'debit_orig' => '100.00', 'debit_usd' => '100.00',
    ]);
}

it('creates a child account under a header', function () {
    Livewire::actingAs(editorUser(RoleId::Accountant))->test(Chart::class)
        ->call('openAdd', '1110')
        ->assertSet('formParent', '1110')
        ->assertSet('formClass', 'assets')
        ->set('formCode', '1199')
        ->set('formNameEn', 'Test safe')
        ->set('formNameAr', 'خزنة تجريبية')
        ->set('formCurrency', 'SYP')
        ->set('formTags', ['Project'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showEditor', false);

    $account = GlAccount::query()->findOrFail('1199');
    expect($account->parent_code)->toBe('1110')
        ->and($account->currency)->toBe('SYP')
        ->and($account->required_tags)->toBe(['Project'])
        ->and($account->is_active)->toBeTrue();
});

it('refuses a duplicate code, a bad code and a class that differs from the parent', function () {
    Livewire::actingAs(editorUser())->test(Chart::class)
        ->call('openAdd', '1110')
        ->set('formCode', '1111')->set('formNameEn', 'X')->set('formNameAr', 'س')
        ->call('save')->assertHasErrors(['formCode']);

    Livewire::actingAs(editorUser())->test(Chart::class)
        ->call('openAdd', '1110')
        ->set('formCode', '12')->set('formNameEn', 'X')->set('formNameAr', 'س')
        ->call('save')->assertHasErrors(['formCode']);

    Livewire::actingAs(editorUser())->test(Chart::class)
        ->call('openAdd', '1110')
        ->set('formCode', '1198')->set('formNameEn', 'X')->set('formNameAr', 'س')->set('formClass', 'expenses')
        ->call('save')->assertHasErrors(['formClass']);

    expect(GlAccount::query()->whereIn('code', ['1198', '12'])->count())->toBe(0);
});

it('edits an account but never its code', function () {
    Livewire::actingAs(editorUser(RoleId::Accountant))->test(Chart::class)
        ->call('openEdit', '1111')
        ->set('formCode', '9999')
        ->call('save')->assertHasErrors(['formCode']);

    Livewire::actingAs(editorUser(RoleId::Accountant))->test(Chart::class)
        ->call('openEdit', '1111')
        ->set('formNameEn', 'Aleppo safe USD')
        ->set('formCity', 'Aleppo')
        ->call('save')->assertHasNoErrors();

    $account = GlAccount::query()->findOrFail('1111');
    expect($account->name_en)->toBe('Aleppo safe USD')->and($account->city)->toBe('Aleppo');
});

it('deactivates and reactivates an account', function () {
    Livewire::actingAs(editorUser(RoleId::Accountant))->test(Chart::class)->call('toggleActive', '1111');
    expect(GlAccount::query()->findOrFail('1111')->is_active)->toBeFalse();

    Livewire::actingAs(editorUser(RoleId::Accountant))->test(Chart::class)->call('toggleActive', '1111');
    expect(GlAccount::query()->findOrFail('1111')->is_active)->toBeTrue();
});

it('deletes an account that was never used, and only for a role that may delete', function () {
    Livewire::actingAs(editorUser(RoleId::Accountant))->test(Chart::class)
        ->call('askDelete', '1111')->assertForbidden();

    Livewire::actingAs(editorUser())->test(Chart::class)
        ->call('askDelete', '1111')
        ->call('delete')
        ->assertSet('showDeleteConfirm', false);

    expect(GlAccount::withTrashed()->where('code', '1111')->exists())->toBeFalse();
});

it('keeps a used account: no delete, no change of class or currency, still renamed and deactivated', function () {
    useAccount('1111');

    Livewire::actingAs(editorUser())->test(Chart::class)->call('askDelete', '1111')->call('delete');
    expect(GlAccount::query()->where('code', '1111')->exists())->toBeTrue();

    Livewire::actingAs(editorUser())->test(Chart::class)
        ->call('openEdit', '1111')->set('formCurrency', 'SYP')->call('save')->assertHasErrors(['formClass']);

    Livewire::actingAs(editorUser())->test(Chart::class)
        ->call('openEdit', '1111')->set('formClass', 'expenses')->call('save')->assertHasErrors(['formClass']);

    Livewire::actingAs(editorUser())->test(Chart::class)
        ->call('openEdit', '1111')->set('formNameEn', 'Renamed')->call('save')->assertHasNoErrors();

    $account = GlAccount::query()->findOrFail('1111');
    expect($account->name_en)->toBe('Renamed')->and($account->currency)->toBe('USD')->and($account->class->value)->toBe('assets');

    Livewire::actingAs(editorUser())->test(Chart::class)->call('toggleActive', '1111');
    expect(GlAccount::query()->findOrFail('1111')->is_active)->toBeFalse();
});

it('refuses to delete a header that still has accounts below it', function () {
    Livewire::actingAs(editorUser())->test(Chart::class)->call('askDelete', '1110')->call('delete');

    expect(GlAccount::query()->where('code', '1110')->exists())->toBeTrue();
});

it('shows balances from approved vouchers only, rolled up to the headers', function () {
    useAccount('1111', 'approved');

    $balances = app(AccountBalances::class)->all();
    expect($balances['1111'])->toBe('100.00')->and($balances['1110'])->toBe('100.00')->and($balances['1000'])->toBe('100.00');

    Voucher::query()->update(['status' => 'pending_approval']);
    expect(app(AccountBalances::class)->all())->toBe([]);
});

it('filters the list by class, posting only and name in English or Arabic', function () {
    Livewire::actingAs(editorUser())->test(Chart::class)
        ->set('classFilter', 'expenses')
        ->assertDontSee('Aleppo main safe')
        ->set('classFilter', '')
        ->set('postingOnly', true)
        ->set('search', 'Aleppo main safe')
        ->assertSee('Aleppo main safe')
        ->set('search', 'مكتب حلب')
        ->assertSee('No account matches.')
        ->set('postingOnly', false)
        ->assertSee('1110');
});
