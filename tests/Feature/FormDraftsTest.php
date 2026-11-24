<?php

use App\Enums\RoleId;
use App\Livewire\VoucherBuilder;
use App\Models\GlProject;
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

    // This test's own project; the chart seeder adds no projects.
    GlProject::query()->create([
        'code' => 'F2F', 'name' => 'Family to Family 2026', 'donor' => 'Caritas Poland', 'restriction' => 'With donor restrictions',
        'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false,
    ]);
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
    $row = ['date' => '2026-03-10', 'kind' => 'income', 'project' => 'F2F', 'budget_line' => '', 'cash' => '1111', 'account' => '4001', 'to_cash' => '', 'amount' => '250', 'rate' => '', 'contact' => '', 'fund_source' => '', 'document' => '', 'description' => 'Grant'];

    return implode("\t", array_map(fn (string $column) => $row[$column] ?? '', OperationSheet::COLUMNS));
}

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

it('has every draft text in English and Arabic', function () {
    $keys = fn (string $locale) => collect(require lang_path("$locale/drafts.php"))->dot()->keys()->sort()->values()->all();

    expect($keys('ar'))->toBe($keys('en'));
});
