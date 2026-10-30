<?php

use App\Enums\FundSource;
use App\Enums\TransferKind;
use App\Enums\VoucherType;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\OperationEntry;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TransferBuilder;
use App\Services\Ledger\TreasuryPosition;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // The seeder keeps only the chart, so Aleppo's two general accounts are made here when they are missing.
    $general = fn (string $code) => [
        'code' => $code, 'name' => $code, 'donor' => null, 'restriction' => 'Without donor restrictions', 'city' => 'Aleppo',
        'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => true, 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('gl_projects')->insertOrIgnore([$general('GEN-ALP-USD'), $general('GEN-ALP-SYP')]);

    // Aleppo's general USD account holds 5,000 USD.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 5000, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
        new LineDraft('4101', 'USD', credit: 5000, projectCode: 'GEN-ALP-USD'),
    ]);
});

function exchangeDraft(string $dollars = '100', string $rate = '15000')
{
    return app(TransferBuilder::class)->build(TransferKind::LocalExchange->value, '2026-04-01', 'Aleppo', '', '', 'USD', $dollars, '1111', '', '', 7, $rate, '', '', '1112');
}

it('saves a currency exchange as its own EXG voucher, numbered EXG-year-number, and keeps its kind', function () {
    $built = exchangeDraft();

    expect($built->problems)->toBe([])
        ->and($built->draft->type)->toBe(VoucherType::Exchange)
        ->and($built->draft->transferKind)->toBe('local_exchange');

    $voucher = app(PostVoucher::class)->post($built->draft);

    expect($voucher->id)->toBe('EXG-2026-0001')
        ->and($voucher->type)->toBe(VoucherType::Exchange)
        ->and($voucher->transfer_kind)->toBe('local_exchange')
        ->and($voucher->type->label())->toBe('Exchange');
});

it('keeps allocations and transfers between projects as TRF, and ALC for payroll', function () {
    expect(VoucherType::Allocation->label())->toBe('Payroll allocation')
        ->and(app(TransferBuilder::class)->build(TransferKind::UsdToProject->value, '2026-04-01', 'Aleppo', 'GEN-ALP-USD', '', 'USD', '10', '1111', '', '', 7)->draft?->type)
        ->not->toBe(VoucherType::Exchange);
});

it('writes an exchange typed as one operation as EXG too', function () {
    $built = app(OperationEntry::class)->build([
        'kind' => 'exchange', 'date' => '2026-04-01', 'cash' => '1111', 'to_cash' => '1112', 'amount' => '100', 'rate' => '15000', 'project' => 'GEN-ALP-USD',
    ], 7);

    expect($built->problems)->toBe([])
        ->and($built->draft->type)->toBe(VoucherType::Exchange);
});

it('measures an approved exchange by its larger leg, and counts its pounds as bought by the general account', function () {
    $voucher = app(PostVoucher::class)->post(exchangeDraft('100', '15000')->draft);
    DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved']);

    expect($voucher->refresh()->approvalAmount())->toBe('100.00');

    $aleppo = collect(app(TreasuryPosition::class)->at()->cities)->firstWhere('city', 'Aleppo');
    expect($aleppo->purchaseCount)->toBe(1)
        ->and($aleppo->purchasedSyp)->toBe('1500000.00');
});
