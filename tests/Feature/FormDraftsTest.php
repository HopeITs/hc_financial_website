<?php

use App\Enums\RoleId;
use App\Livewire\MultiEntry;
use App\Livewire\SingleEntry;
use App\Livewire\VoucherBuilder;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\OperationSheet;
use App\Support\FormDrafts;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (new ChartOfAccountsSeeder)->run();
    Storage::fake('local');
});

function draftUser(RoleId $role = RoleId::Accountant, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function draftRow(int $userId, string $screen): ?object
{
    return DB::table('web_form_drafts')->where('user_id', $userId)->where('screen', $screen)->first();
}

function draftSheetRow(): string
{
    $row = ['date' => '2026-03-10', 'kind' => 'income', 'project' => 'F2F', 'budget_line' => '', 'cash' => '1111', 'account' => '4101', 'to_cash' => '', 'amount' => '250', 'rate' => '', 'contact' => '', 'fund_source' => '', 'document' => '', 'description' => 'Grant'];

    return implode("\t", array_map(fn (string $column) => $row[$column], OperationSheet::COLUMNS));
}

it('saves a single entry as a draft, offers it back, restores it, and forgets it once the entry is saved', function () {
    $ann = draftUser();

    Livewire::actingAs($ann)->test(SingleEntry::class)
        ->set('kind', 'income')->set('date', '2026-03-10')->set('project', 'F2F')->set('cash', '1111')->set('account', '4101')->set('amount', '250')
        ->set('description', 'مسودة منحة')
        ->call('saveDraft')
        ->assertDispatched('form-clean')
        ->assertSee(__('drafts.saved', ['time' => now()->format('H:i')]));

    expect(json_decode(draftRow($ann->getKey(), 'single')->payload, true))->toMatchArray(['amount' => '250', 'description' => 'مسودة منحة']);

    $page = Livewire::actingAs($ann)->test(SingleEntry::class)
        ->assertSeeHtml('data-test="draft-offer"')
        ->assertSet('amount', '')
        ->call('restoreDraft')
        ->assertSet('amount', '250')->assertSet('cash', '1111')->assertSet('description', 'مسودة منحة')
        ->assertSee(__('drafts.restored_files'))
        ->assertDontSeeHtml('data-test="draft-offer"');

    $page->call('save')->assertSet('failures', []);

    expect(Voucher::query()->count())->toBe(1)
        ->and(draftRow($ann->getKey(), 'single'))->toBeNull();
});

it('keeps each person their own draft, and deletes it on request', function () {
    $ann = draftUser();
    $ben = draftUser(name: 'Ben');

    Livewire::actingAs($ann)->test(SingleEntry::class)->set('amount', '75')->call('saveDraft');

    Livewire::actingAs($ben)->test(SingleEntry::class)->assertSet('draftSavedAt', null)->assertDontSeeHtml('data-test="draft-offer"');

    Livewire::actingAs($ann)->test(SingleEntry::class)->call('discardDraft')->assertSee(__('drafts.discarded'));
    expect(draftRow($ann->getKey(), 'single'))->toBeNull();
});

it('restores only the fields a form keeps, with their own type', function () {
    $ann = draftUser();
    app(FormDrafts::class)->save($ann->getKey(), 'single', ['amount' => ['not', 'text'], 'rate' => '1.5', 'failures' => ['x'], 'unknown' => 'y']);

    Livewire::actingAs($ann)->test(SingleEntry::class)->call('restoreDraft')
        ->assertSet('amount', '')
        ->assertSet('rate', '1.5')
        ->assertSet('failures', []);
});

it('gives restored voucher builder lines exactly the builder fields', function () {
    $ann = draftUser();
    app(FormDrafts::class)->save($ann->getKey(), 'voucher-builder', ['type' => 'CPV', 'lines' => [['account' => '1111', 'amount' => 40, 'side' => 'credit', 'evil' => '<b>'], 'not a line']]);

    $page = Livewire::actingAs($ann)->test(VoucherBuilder::class)->call('restoreDraft')->assertSet('type', 'CPV');
    $lines = $page->get('lines');

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toMatchArray(['account' => '1111', 'amount' => '40', 'side' => 'credit', 'currency' => 'USD'])
        ->and($lines[0])->not->toHaveKey('evil');

    app(FormDrafts::class)->save($ann->getKey(), 'voucher-builder', ['lines' => []]);
    expect(Livewire::actingAs($ann)->test(VoucherBuilder::class)->call('restoreDraft')->get('lines'))->toHaveCount(2);
});

it('drafts a pasted sheet on multi entry and forgets it once the sheet is saved', function () {
    $ann = draftUser();

    Livewire::actingAs($ann)->test(MultiEntry::class)->set('sheet', draftSheetRow())->call('saveDraft');

    Livewire::actingAs($ann)->test(MultiEntry::class)->call('restoreDraft')->assertSet('sheet', draftSheetRow())->assertSee(__('drafts.restored'))
        ->call('save');

    expect(Voucher::query()->count())->toBe(1)
        ->and(draftRow($ann->getKey(), 'multi'))->toBeNull();
});

it('refuses a draft that is too large, and a draft from someone who may not enter', function () {
    $ann = draftUser();

    Livewire::actingAs($ann)->test(MultiEntry::class)->set('sheet', str_repeat('x', FormDrafts::MAX_BYTES + 1))->call('saveDraft')->assertSee(__('drafts.too_big'));
    expect(draftRow($ann->getKey(), 'multi'))->toBeNull();

    Livewire::actingAs(draftUser(RoleId::Employee, 'Emp'))->test(SingleEntry::class)->call('saveDraft')->assertForbidden();
});

it('puts the leave warning and the draft button on the entry screens', function () {
    $ann = draftUser();

    foreach ([SingleEntry::class, VoucherBuilder::class, MultiEntry::class] as $screen) {
        Livewire::actingAs($ann)->test($screen)->assertSeeHtml('x-data="unsavedGuard"')->assertSeeHtml('data-test="save-draft"')->assertSeeHtml('x-on:click="leaving()"');
    }
});

it('has every draft text in English and Arabic', function () {
    $keys = fn (string $locale) => collect(require lang_path("$locale/drafts.php"))->dot()->keys()->sort()->values()->all();

    expect($keys('ar'))->toBe($keys('en'));
});
