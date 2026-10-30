<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherSource;
use App\Enums\VoucherType;
use App\Livewire\Chart;
use App\Models\User;
use App\Services\Ledger\FinancialStatements;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\NetAssets;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // The seeder keeps only the chart, so the projects are made here.
    $project = fn (string $code, bool $general) => [
        'code' => $code, 'name' => $code, 'donor' => $general ? null : 'Donor', 'restriction' => $general ? 'Without donor restrictions' : NetAssets::RESTRICTED,
        'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => $general, 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('gl_projects')->insert([$project('F2F', false), $project('GEN-ALP-USD', true)]);
    DB::table('gl_budget_lines')->insert(['key' => 'F2F|Bank charges', 'project_code' => 'F2F', 'donor' => 'Donor', 'name' => 'Bank charges', 'account_code' => '5501', 'budget' => 1000, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()]);

    // The general account receives 5,000 USD and a restricted project 1,000 USD; the project then spends 120 USD,
    // and 400 USD of the general money is allocated to it (cash moves owner, no income or expense).
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 5000, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury, restriction: 'Without donor restrictions'),
        new LineDraft('4101', 'USD', credit: 5000, projectCode: 'GEN-ALP-USD', restriction: 'Without donor restrictions'),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-02'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 1000, projectCode: 'F2F', fundSource: FundSource::DonorGrant, restriction: NetAssets::RESTRICTED),
        new LineDraft('4101', 'USD', credit: 1000, projectCode: 'F2F', restriction: NetAssets::RESTRICTED),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-05'), 'type' => VoucherType::Payment], [
        new LineDraft('5501', 'USD', debit: 120, projectCode: 'F2F', budgetLineKey: 'F2F|Bank charges', restriction: NetAssets::RESTRICTED),
        new LineDraft('1111', 'USD', credit: 120, projectCode: 'F2F', fundSource: FundSource::DonorGrant, restriction: NetAssets::RESTRICTED),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-06'), 'type' => VoucherType::Transfer], [
        new LineDraft('1111', 'USD', debit: 400, projectCode: 'F2F', fundSource: FundSource::DonorGrant, restriction: NetAssets::RESTRICTED),
        new LineDraft('1111', 'USD', credit: 400, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury, restriction: 'Without donor restrictions'),
    ]);
});

it('computes net assets as assets minus liabilities, split by donor restriction', function () {
    expect(app(NetAssets::class)->asAt())->toBe(['total' => '5880.00', 'restricted' => '880.00', 'unrestricted' => '5000.00'])
        ->and(app(NetAssets::class)->asAt('2026-03-03'))->toBe(['total' => '6000.00', 'restricted' => '1000.00', 'unrestricted' => '5000.00']);
});

it('agrees with the statement of financial position', function () {
    $position = app(FinancialStatements::class)->position(null);
    $netAssets = collect($position['sections'])->firstWhere('key', 'net_assets');

    expect($position['balanced'])->toBeTrue()
        ->and($netAssets['current'])->toBe(app(NetAssets::class)->asAt()['total']);
});

it('shows the computed figures on the chart, the trial balance and the statements', function () {
    $user = User::query()->findOrFail(createFinanceUser('Hadi', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    Livewire::actingAs($user)->test(Chart::class)
        ->assertViewHas('balances', fn (array $balances) => $balances['3000'] === '5880.00' && $balances['3100'] === '5000.00' && $balances['3200'] === '880.00')
        ->assertSeeHtml('data-test="computed-3000"');

    $this->actingAs($user)->get(route('reports.trial-balance'))->assertOk()->assertSeeHtml('data-test="net-assets"')->assertSee('5,880.00');
    $this->actingAs($user)->get(route('reports.financial-statements'))->assertOk()->assertSeeHtml('data-test="net-assets-restricted"');
});

it('refuses a line typed on a net assets account, but keeps a migrated voucher as it was', function () {
    $lines = [
        new LineDraft('1111', 'USD', debit: 50, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
        new LineDraft('3101', 'USD', credit: 50, projectCode: 'GEN-ALP-USD'),
    ];
    $draft = fn (VoucherSource $source) => new VoucherDraft(VoucherType::Journal, CarbonImmutable::parse('2026-03-10'), 1, $lines, source: $source, legacyActionId: $source === VoucherSource::Migration ? 77 : null);

    $web = app(PostVoucher::class)->check($draft(VoucherSource::Web));
    expect(collect($web)->pluck('rule')->all())->toContain(16);

    $migrated = app(PostVoucher::class)->check($draft(VoucherSource::Migration));
    expect(collect($migrated)->pluck('rule')->all())->not->toContain(16);
});
