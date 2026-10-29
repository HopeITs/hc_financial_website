<?php

use App\Enums\RoleId;
use App\Livewire\Chart;
use App\Livewire\CurrencySettings;
use App\Livewire\Programs;
use App\Models\Currency;
use App\Models\GlAccount;
use App\Models\GlProgram;
use App\Models\User;
use App\Support\FormDrafts;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Save as draft (U-04) on the chart editor, the currency form and the program form: the draft is offered back on a fresh
 * page, restores the form as it was, and is forgotten once the form is saved.
 */

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
});

function fdAUser(): User
{
    return User::query()->findOrFail(createFinanceUser('Hana', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
}

function fdARow(User $user, string $screen): ?object
{
    return DB::table('web_form_drafts')->where('user_id', $user->getKey())->where('screen', $screen)->first();
}

function fdAAccount(string $code = '1199'): GlAccount
{
    return GlAccount::query()->create([
        'code' => $code, 'parent_code' => null, 'level' => 'posting', 'class' => 'assets', 'name_en' => 'Test safe', 'name_ar' => 'خزنة تجريبية',
        'normal_side' => 'debit', 'currency' => 'USD', 'is_cash' => false, 'required_tags' => [], 'is_active' => true,
    ]);
}

it('drafts a new chart account, restores it on a fresh page, and forgets it once the account is saved', function () {
    $hana = fdAUser();

    Livewire::actingAs($hana)->test(Chart::class)
        ->call('openAdd')
        ->set('formCode', '1198')->set('formNameEn', 'Draft safe')->set('formNameAr', 'خزنة مسودة')->set('formCurrency', 'SYP')->set('formTags', ['Project'])
        ->call('saveDraft')
        ->assertDispatched('form-clean');

    $page = Livewire::actingAs($hana)->test(Chart::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->assertSet('formNameEn', '')
        ->call('restoreDraft')
        ->assertSet('showEditor', true)->assertSet('editing', null)->assertSet('formCode', '1198')->assertSet('formNameAr', 'خزنة مسودة')
        ->assertSet('formCurrency', 'SYP')->assertSet('formTags', ['Project']);

    $page->call('save')->assertHasNoErrors()->assertSet('showEditor', false)->assertDispatched('form-clean');

    expect(GlAccount::query()->find('1198')?->currency)->toBe('SYP')
        ->and(fdARow($hana, 'chart'))->toBeNull();
});

it('brings back the chart account being edited only while it exists, and only known tags', function () {
    $hana = fdAUser();
    fdAAccount();

    Livewire::actingAs($hana)->test(Chart::class)->call('openEdit', '1199')->set('formNameEn', 'Renamed safe')->call('saveDraft');

    Livewire::actingAs($hana)->test(Chart::class)->call('restoreDraft')->assertSet('editing', '1199')->assertSet('formNameEn', 'Renamed safe');

    app(FormDrafts::class)->save($hana->getKey(), 'chart', ['editing' => '9999', 'formTags' => ['Project', 'evil', 5], 'showEditor' => true]);

    Livewire::actingAs($hana)->test(Chart::class)->call('restoreDraft')->assertSet('editing', null)->assertSet('formTags', ['Project']);
});

it('drafts a new currency, restores it on a fresh page, and forgets it once the currency is saved', function () {
    $hana = fdAUser();

    Livewire::actingAs($hana)->test(CurrencySettings::class)
        ->call('add')
        ->set('code', 'GBP')->set('nameEn', 'Pound sterling')->set('nameAr', 'جنيه إسترليني')->set('quote', Currency::USD_PER_UNIT)
        ->call('saveDraft')
        ->assertDispatched('form-clean');

    $page = Livewire::actingAs($hana)->test(CurrencySettings::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->assertSet('showForm', false)
        ->call('restoreDraft')
        ->assertSet('showForm', true)->assertSet('editing', null)->assertSet('code', 'GBP')->assertSet('nameAr', 'جنيه إسترليني')->assertSet('quote', Currency::USD_PER_UNIT);

    $page->call('save')->assertHasNoErrors()->assertSet('showForm', false);

    expect(Currency::query()->find('GBP')?->quote)->toBe(Currency::USD_PER_UNIT)
        ->and(fdARow($hana, 'currency'))->toBeNull();
});

it('brings back the currency being edited, but never the base currency', function () {
    $hana = fdAUser();

    Livewire::actingAs($hana)->test(CurrencySettings::class)->call('edit', 'EUR')->set('nameEn', 'Euro (draft)')->call('saveDraft');

    Livewire::actingAs($hana)->test(CurrencySettings::class)->call('restoreDraft')->assertSet('editing', 'EUR')->assertSet('nameEn', 'Euro (draft)');

    app(FormDrafts::class)->save($hana->getKey(), 'currency', ['editing' => 'USD', 'showForm' => true]);

    Livewire::actingAs($hana)->test(CurrencySettings::class)->call('restoreDraft')->assertSet('editing', null);
});

it('drafts a new program, restores it on a fresh page, and forgets it once the program is saved', function () {
    $hana = fdAUser();

    Livewire::actingAs($hana)->test(Programs::class)
        ->call('openForm')
        ->set('nameEn', 'Water')->set('nameAr', 'المياه')->set('description', 'Clean water')
        ->call('saveDraft')
        ->assertDispatched('form-clean');

    $page = Livewire::actingAs($hana)->test(Programs::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->call('restoreDraft')
        ->assertSet('showForm', true)->assertSet('nameEn', 'Water')->assertSet('nameAr', 'المياه')->assertSet('description', 'Clean water');

    $page->call('save')->assertHasNoErrors()->assertSet('showForm', false);

    expect(GlProgram::query()->where('name_en', 'Water')->exists())->toBeTrue()
        ->and(fdARow($hana, 'programs'))->toBeNull();
});

it('puts the leave warning and the draft button on the chart, currency and program forms', function () {
    $hana = fdAUser();

    Livewire::actingAs($hana)->test(Chart::class)->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('data-test="save-draft"')->assertSeeHtml('x-on:click="leaving()"');
    Livewire::actingAs($hana)->test(CurrencySettings::class)->call('add')->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('data-test="save-draft"')->assertSeeHtml('x-on:click="leaving()"');
    Livewire::actingAs($hana)->test(Programs::class)->call('openForm')->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('data-test="save-draft"')->assertSeeHtml('x-on:click="leaving()"');
});
