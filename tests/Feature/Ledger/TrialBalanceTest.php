<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\TrialBalance;
use App\Models\User;
use App\Services\Ledger\AccountStatement;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TrialBalance as TrialBalanceReport;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function tbReceipt(string $date, int $amount, bool $approve = true): void
{
    $voucher = app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse($date)], [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: 'F2F'),
    ]));

    if ($approve) {
        DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);
    }
}

it('groups accounts by class, balances Dr and Cr, and counts approved vouchers up to the date only', function () {
    tbReceipt('2026-01-05', 100);
    tbReceipt('2026-02-10', 40);
    tbReceipt('2026-03-01', 7);
    tbReceipt('2026-02-11', 999, approve: false);

    $data = app(TrialBalanceReport::class)->asAt('2026-02-28');

    expect(array_column($data['classes'], 'class'))->toBe(['assets', 'income'])
        ->and($data['debit'])->toBe('140.00')
        ->and($data['credit'])->toBe('140.00')
        ->and($data['balanced'])->toBeTrue()
        ->and($data['classes'][0]['rows'][0]['net'])->toBe('140.00');
});

it('gives each account the same net as its statement closing balance', function () {
    tbReceipt('2026-01-05', 100);
    tbReceipt('2026-02-10', 40);

    $row = app(TrialBalanceReport::class)->asAt('2026-02-28')['classes'][0]['rows'][0];

    expect($row['net'])->toBe(app(AccountStatement::class)->for($row['code'], null, null, '2026-02-28')['closing']);
});

it('converts only USD in the SYP view', function () {
    tbReceipt('2026-01-05', 100);

    $data = app(TrialBalanceReport::class)->asAt(null, 'SYP', '15000');

    expect($data['classes'][0]['rows'][0]['debit'])->toBe('1500000.00')
        ->and($data['balanced'])->toBeTrue();
});

it('opens for a signed-in user and exports CSV', function () {
    tbReceipt('2026-02-10', 40);
    $user = User::query()->findOrFail(createFinanceUser('tbuser', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    $this->actingAs($user)->get('/screens/tb')->assertOk()->assertSee('1111');

    Livewire::actingAs($user)->test(TrialBalance::class)->call('export')->assertFileDownloaded('trial-balance.csv');
});
