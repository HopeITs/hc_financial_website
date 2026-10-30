<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\Tills;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\TillOwners;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    GlProject::query()->create(['code' => 'F2F', 'name' => 'Face to Face', 'restriction' => 'restricted', 'legacy_account_id' => 77]);
    GlProject::query()->create(['code' => 'ABC', 'name' => 'Other', 'restriction' => 'restricted', 'legacy_account_id' => 78]);
});

function tillReceipt(string $project, int $amount): void
{
    ledgerApprovedVoucher([], [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: $project, fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: $project),
    ]);
}

it('splits a till by its owners and the rows add up to the balance exactly', function () {
    tillReceipt('F2F', 100);
    tillReceipt('ABC', 40);

    $till = collect(app(TillOwners::class)->tills())->firstWhere('code', '1111');

    expect($till['balance'])->toBe('140.00')
        ->and(collect($till['owners'])->pluck('amount', 'project_code')->all())->toBe(['ABC' => '40.00', 'F2F' => '100.00'])
        ->and($till['unowned'])->toBe('0.00')
        ->and(collect($till['owners'])->reduce(fn ($carry, $owner) => bcadd($carry, $owner['usd'], 2), '0.00'))->toBe($till['balance_usd']);
});

it('shows only the owners asked for', function () {
    tillReceipt('F2F', 100);
    tillReceipt('ABC', 40);

    $till = collect(app(TillOwners::class)->tills(['ABC']))->firstWhere('code', '1111');

    expect(array_column($till['owners'], 'project_code'))->toBe(['ABC']);
});

it('opens the screen with a block for each till', function () {
    tillReceipt('F2F', 100);
    $user = User::query()->findOrFail(createFinanceUser('Fay', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    Livewire::actingAs($user)->test(Tills::class)
        ->assertSeeHtml('data-test="till-1111"')
        ->assertSeeHtml('data-test="till-balance"')
        ->assertDontSeeHtml('data-test="tills-limited"');
});
