<?php

use App\Enums\RoleId;
use App\Livewire\MultiAttachments;
use App\Livewire\Requests;
use App\Livewire\Users;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Support\FormDrafts;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    if (! Schema::hasColumn('gl_projects', 'contract_number')) {
        (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    }
    (new ChartOfAccountsSeeder)->run();
    GlBudgetLine::query()->delete();
    GlProject::query()->delete();
    Storage::fake('local');
});

function fdCUser(RoleId $role = RoleId::HeadOffice, string $name = 'Hoa'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function fdCDraft(int $userId, string $screen): ?array
{
    $payload = DB::table('web_form_drafts')->where('user_id', $userId)->where('screen', $screen)->value('payload');

    return $payload === null ? null : json_decode($payload, true);
}

it('saves the users form as a draft without any password, restores it, and forgets it once access is saved', function () {
    $hoa = fdCUser();
    $ann = fdCUser(RoleId::Accountant, 'Ann');
    DB::table('role')->insertOrIgnore(['ID' => RoleId::FinancialManager->value, 'Name' => 'Financial Manager']);

    Livewire::actingAs($hoa)->test(Users::class)
        ->call('selectUser', $ann->getKey())
        ->set('primaryRoleId', RoleId::FinancialManager->value)->set('permissions', ['vouchers.view'])
        ->set('password', 'a new strong one')->set('confirmation', 'a new strong one')
        ->call('saveDraft')
        ->assertDispatched('form-clean');

    $stored = DB::table('web_form_drafts')->where('user_id', $hoa->getKey())->where('screen', 'users')->value('payload');
    expect($stored)->not->toContain('a new strong one')
        ->and(json_decode($stored, true))->not->toHaveKeys(['password', 'confirmation', 'newPassword'])
        ->and(json_decode($stored, true))->toMatchArray(['editing' => $ann->getKey(), 'primaryRoleId' => RoleId::FinancialManager->value]);

    $page = Livewire::actingAs($hoa)->test(Users::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->assertSet('editing', null)
        ->call('restoreDraft')
        ->assertSet('editing', $ann->getKey())->assertSet('primaryRoleId', RoleId::FinancialManager->value)
        ->assertSet('permissions', ['vouchers.view'])->assertSet('password', '')
        ->assertSee('Ann');

    $page->call('saveAccess')->assertSet('failures', [])->assertDispatched('form-clean');

    expect((int) DB::table('user')->where('ID', $ann->getKey())->value('Role'))->toBe(RoleId::FinancialManager->value)
        ->and(fdCDraft($hoa->getKey(), 'users'))->toBeNull();
});

it('reopens the add-user dialog from a draft, never with the password, and drops what no longer exists', function () {
    $hoa = fdCUser();

    Livewire::actingAs($hoa)->test(Users::class)
        ->call('openCreate')->set('newUsername', 'Newbie')->set('newPassword', 'secret new pass')->set('newRoleId', RoleId::Accountant->value)
        ->call('saveDraft');

    expect(fdCDraft($hoa->getKey(), 'users'))->not->toHaveKey('newPassword');

    Livewire::actingAs($hoa)->test(Users::class)->call('restoreDraft')
        ->assertSet('showCreate', true)->assertSet('newUsername', 'Newbie')->assertSet('newRoleId', RoleId::Accountant->value)->assertSet('newPassword', '');

    app(FormDrafts::class)->save($hoa->getKey(), 'users', ['editing' => 9999, 'primaryRoleId' => 777, 'extraRoleIds' => [RoleId::Cashier->value, 'x', 777], 'permissions' => ['vouchers.view', 'nowhere.fly', 3]]);

    Livewire::actingAs($hoa)->test(Users::class)->call('restoreDraft')
        ->assertSet('editing', null)->assertSet('primaryRoleId', null)
        ->assertSet('extraRoleIds', [RoleId::Cashier->value])->assertSet('permissions', ['vouchers.view']);
});

it('puts the leave warning on all three screens and the draft button on users', function () {
    $hoa = fdCUser();
    $ann = fdCUser(RoleId::Accountant, 'Ann');

    Livewire::actingAs($hoa)->test(Users::class)->call('selectUser', $ann->getKey())
        ->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('data-test="save-draft"')->assertSeeHtml('x-on:click="leaving()"');

    Livewire::actingAs($hoa)->test(Requests::class)->call('newRequest')
        ->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('x-on:click="leaving()"')->assertDontSeeHtml('data-test="save-draft"');

    Livewire::actingAs($hoa)->test(MultiAttachments::class)
        ->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('x-on:click="leaving()"')->assertDontSeeHtml('data-test="save-draft"');
});
