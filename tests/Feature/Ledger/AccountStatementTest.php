<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\Statement;
use App\Models\User;
use App\Services\Ledger\AccountStatement;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function receiptOn(string $date, int $amount, string $project = 'F2F', bool $approve = true): string
{
    $voucher = app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse($date)], [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: $project, fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: $project),
    ]));

    if ($approve) {
        DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);
    }

    return $voucher->id;
}

it('carries an opening balance and a running balance, approved vouchers only', function () {
    receiptOn('2026-01-05', 100);
    receiptOn('2026-02-10', 40);
    receiptOn('2026-02-20', 25);
    receiptOn('2026-02-25', 999, approve: false);

    $data = app(AccountStatement::class)->for('1111', null, '2026-02-01', '2026-02-28');

    expect($data['opening'])->toBe('100.00')
        ->and(array_column($data['rows'], 'balance'))->toBe(['140.00', '165.00'])
        ->and($data['debit'])->toBe('65.00')
        ->and($data['closing'])->toBe('165.00');
});

it('filters by project', function () {
    receiptOn('2026-02-10', 40, 'F2F');

    expect(app(AccountStatement::class)->for('1111', 'F2F')['closing'])->toBe('40.00')
        ->and(app(AccountStatement::class)->for('1111', 'NOPE')['closing'])->toBe('0.00');
});

it('shows a header account with every account below it', function () {
    receiptOn('2026-02-10', 40);

    expect(app(AccountStatement::class)->for('1000')['closing'])->toBe('40.00');
});

it('ends on the same balance as the trial balance of the account at that date', function () {
    receiptOn('2026-01-05', 100);
    receiptOn('2026-02-10', 40);
    receiptOn('2026-03-01', 7);

    $asAt = '2026-02-28';
    $tb = DB::table('gl_voucher_lines as l')->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
        ->where('v.status', 'approved')->where('l.account_code', '1111')->where('v.date', '<=', $asAt)
        ->selectRaw('sum(l.debit_usd) - sum(l.credit_usd) as net')->value('net');

    expect(app(AccountStatement::class)->for('1111', null, null, $asAt)['closing'])->toBe(number_format((float) $tb, 2, '.', ''));
});

it('opens for a signed-in user, links the vouchers and exports CSV', function () {
    $id = receiptOn('2026-02-10', 40);
    $user = User::query()->findOrFail(createFinanceUser('stmt', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    $this->actingAs($user)->get('/screens/statement?account=1111')->assertOk()->assertSee($id);

    Livewire::actingAs($user)->withQueryParams(['account' => '1111'])->test(Statement::class)
        ->call('export')->assertFileDownloaded('statement-1111.csv');
});
