<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\LineDraft;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
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

    // The seeder keeps only the chart, so the projects these operations use are made here.
    $project = fn (string $code, bool $general = false) => [
        'code' => $code, 'name' => $code, 'donor' => $general ? null : 'Donor', 'restriction' => $general ? 'Without donor restrictions' : 'With donor restrictions',
        'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => $general, 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('gl_projects')->insert([$project('F2F'), $project('SZ'), $project('GEN-ALP-USD', true), $project('GEN-ALP-SYP', true)]);
    DB::table('gl_budget_lines')->insert([
        'key' => 'F2F|Bank charges', 'project_code' => 'F2F', 'donor' => 'Donor', 'name' => 'Bank charges', 'account_code' => '5501',
        'budget' => 1000, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Aleppo's general USD account holds 5,000 USD, and F2F 1,000 USD, both in the main safe.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 5000, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
        new LineDraft('4001', 'USD', credit: 5000, projectCode: 'GEN-ALP-USD'),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-02'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 1000, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4001', 'USD', credit: 1000, projectCode: 'F2F'),
    ]);
});

function operator(RoleId $role = RoleId::Accountant, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** The voucher an operation saved: the setup's vouchers are approved, the saved ones wait. */
function pendingVoucher(?string $kind = null): Voucher
{
    return Voucher::query()->where('status', 'pending_approval')->when($kind !== null, fn ($query) => $query->where('transfer_kind', $kind))->sole();
}

/** @return list<array<string, mixed>> */
function savedLines(Voucher $voucher): array
{
    return DB::table('gl_voucher_lines')->where('voucher_id', $voucher->id)->orderBy('line_no')->get()->map(fn ($line) => (array) $line)->all();
}

it('gives the menu the payment voucher in place of Operations, and the old addresses lead to it', function () {
    $ann = operator();

    expect(Navigation::path('single'))->toBe('entries/payment');

    $this->actingAs($ann)->get('/entries/single')->assertRedirect('/entries/payment');
    $this->actingAs($ann)->get('/entries/operations')->assertRedirect('/entries/payment');
    $this->actingAs($ann)->get('/entries/payment')->assertOk()->assertSee('Payment voucher');
});
