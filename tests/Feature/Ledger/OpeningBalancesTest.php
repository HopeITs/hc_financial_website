<?php

use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\OpeningBalances as OpeningScreen;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\NetAssets;
use App\Services\Ledger\OpeningBalances;
use App\Services\Ledger\PostVoucher;
use App\Support\Navigation;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // The seeder keeps only the chart, so Aleppo's owners are made here.
    $owner = fn (string $code, string $restriction, bool $general = false) => [
        'code' => $code, 'name' => $code, 'donor' => $general ? null : 'Donor', 'restriction' => $restriction, 'city' => 'Aleppo',
        'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => $general, 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('gl_projects')->insert([
        $owner('GEN-ALP-USD', 'Without donor restrictions', true),
        $owner('GEN-ALP-SYP', 'Without donor restrictions', true),
        $owner('F2F', NetAssets::RESTRICTED),
        $owner('ODD', ''),
    ]);
});

/** Aleppo's opening: 5,000 USD and 15M pounds (1,000 USD) held by the general account, 1,000 USD by F2F, which owes 200 USD. */
function aleppoRows(array $extra = []): array
{
    return [
        ['account' => '1111', 'project' => 'GEN-ALP-USD', 'amount' => '5,000', 'rate' => ''],
        ['account' => '1112', 'project' => 'GEN-ALP-SYP', 'amount' => '15000000', 'rate' => '15000'],
        ['account' => '1111', 'project' => 'F2F', 'amount' => '1000', 'rate' => ''],
        ['account' => '2101', 'project' => 'F2F', 'amount' => '200', 'rate' => '', 'contact' => 'Supplier'],
        ...$extra,
    ];
}

it('writes the net assets of each owner itself, on 3101 or 3201 by its restriction, so the voucher balances', function () {
    $built = app(OpeningBalances::class)->build('Aleppo', '2026-01-01', aleppoRows(), 7);

    expect($built->problems)->toBe([])
        ->and($built->draft->type)->toBe(VoucherType::Opening)
        ->and($built->netAssets)->toBe(['unrestricted' => '6000.00', 'restricted' => '800.00', 'unknown' => '0.00'])
        ->and(app(PostVoucher::class)->check($built->draft))->toBe([]);

    $netLines = collect($built->draft->lines)->filter(fn ($line) => str_starts_with($line->accountCode, '3'))
        ->map(fn ($line) => [$line->accountCode, $line->projectCode, $line->creditUsd])->values()->all();
    expect($netLines)->toBe([['3101', 'GEN-ALP-USD', '5000.00'], ['3101', 'GEN-ALP-SYP', '1000.00'], ['3201', 'F2F', '800.00']]);

    $voucher = app(PostVoucher::class)->post($built->draft);
    DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved']);

    expect($voucher->id)->toBe('OPN-2026-0001')
        ->and(app(NetAssets::class)->asAt())->toBe(['total' => '6800.00', 'restricted' => '800.00', 'unrestricted' => '6000.00']);
});

it('opens a city once', function () {
    $first = app(OpeningBalances::class)->build('Aleppo', '2026-01-01', aleppoRows(), 7);
    $voucher = app(PostVoucher::class)->post($first->draft);

    expect(app(OpeningBalances::class)->existing('Aleppo')?->id)->toBe($voucher->id)
        ->and(app(OpeningBalances::class)->build('Aleppo', '2026-01-02', aleppoRows(), 7)->problems)
        ->toBe([__('ledger.opening.already', ['city' => 'Aleppo', 'voucher' => $voucher->id])]);
});

it('refuses an opening whose net assets would land on 3901', function () {
    $built = app(OpeningBalances::class)->build('Aleppo', '2026-01-01', aleppoRows([['account' => '1111', 'project' => 'ODD', 'amount' => '50']]), 7);

    expect($built->draft)->toBeNull()
        ->and($built->problems)->toBe([__('ledger.opening.suspense_not_zero', ['amount' => '50.00'])]);
});

it('says what is wrong with a line', function () {
    $built = app(OpeningBalances::class)->build('Aleppo', '2026-01-01', [
        ['account' => '1112', 'project' => 'GEN-ALP-SYP', 'amount' => '1000', 'rate' => ''],
        ['account' => '5501', 'project' => 'F2F', 'amount' => '10'],
        ['account' => '1111', 'project' => '', 'amount' => '10'],
        ['account' => '1111', 'project' => 'F2F', 'amount' => '0'],
    ], 7);

    expect($built->problems)->toBe([
        __('ledger.opening.bad_rate', ['line' => 1, 'currency' => 'SYP']),
        __('ledger.opening.bad_account', ['line' => 2, 'account' => '5501']),
        __('ledger.opening.bad_project', ['line' => 3]),
        __('ledger.opening.bad_amount', ['line' => 4]),
    ]);
});

it('lets Head Office save the opening voucher from the screen, and keeps the accountant out', function () {
    $head = User::query()->findOrFail(createFinanceUser('Hadi', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
    $accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));

    $this->actingAs($head)->get(route(Navigation::routeName('opening')))->assertOk()->assertSeeHtml('data-test="opening-rows"');
    $this->actingAs($accountant)->get(route(Navigation::routeName('opening')))->assertForbidden();

    Livewire::actingAs($head)->test(OpeningScreen::class)
        ->assertSet('city', 'Aleppo')
        ->set('date', '2026-01-01')
        ->set('rows', aleppoRows())
        ->assertSeeHtml('data-test="opening-lines"')
        ->assertSeeHtml('data-test="ready"')
        ->call('save')
        ->assertSet('failures', [])
        ->assertRedirect(route('entries.vouchers', ['voucher' => 'OPN-2026-0001']));

    expect(Voucher::query()->where('type', 'OPN')->count())->toBe(1);

    Livewire::actingAs($head)->test(OpeningScreen::class)->assertSeeHtml('data-test="opening-exists"');
});
