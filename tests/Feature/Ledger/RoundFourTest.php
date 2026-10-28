<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\TransferKind;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\FollowUpReport;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\FollowUpFilters;
use App\Services\Ledger\FollowUpReports;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TransferBuilder;
use App\Services\Ledger\VoucherWarnings;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // Aleppo's general USD account has 5,000 USD in the cash box.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 5000, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
        new LineDraft('4101', 'USD', credit: 5000, projectCode: 'GEN-ALP-USD'),
    ]);
});

function localExchange(array $with = [])
{
    $with += ['amount' => '1000', 'rate' => '15000', 'received' => '', 'commission' => '', 'usd' => '1111', 'syp' => '1112', 'city' => 'Aleppo'];

    return app(TransferBuilder::class)->build(
        'local_exchange', '2026-04-01', $with['city'], '', '', 'SYP', $with['amount'], $with['usd'], '', '', 7,
        $with['rate'], $with['received'], $with['commission'], $with['syp'],
    );
}

function approveVoucher(Voucher $voucher): void
{
    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => 9, 'approved_at' => now()]);
}

it('exchanges dollars for pounds at the typed rate in one voucher with no difference', function () {
    $built = localExchange();

    expect($built->problems)->toBe([])
        ->and($built->draft->transferKind)->toBe(TransferKind::LocalExchange->value)
        ->and($built->draft->lines)->toHaveCount(2);

    [$usd, $syp] = $built->draft->lines;
    expect($usd->accountCode)->toBe('1111')->and($usd->projectCode)->toBe('GEN-ALP-USD')->and($usd->creditUsd)->toBe('1000.00')
        ->and($syp->accountCode)->toBe('1112')->and($syp->projectCode)->toBe('GEN-ALP-SYP')
        ->and($syp->currency)->toBe('SYP')->and($syp->debitUsd)->toBe('1000.00');

    $voucher = app(PostVoucher::class)->post($built->draft);
    expect($voucher->transfer_kind)->toBe('local_exchange')->and($voucher->lines)->toHaveCount(2);
});

it('books a shortfall of pounds and the commission on 5503', function () {
    // 1,000 USD at 15,000 should give 15,000,000 pounds; only 14,850,000 arrive (worth 990 USD), and 5 USD commission is paid.
    $built = localExchange(['received' => '14850000', 'commission' => '5']);

    expect($built->problems)->toBe([]);

    $voucher = app(PostVoucher::class)->post($built->draft);
    $fees = $voucher->lines->where('account_code', '5503');

    expect($fees)->toHaveCount(2)
        ->and((float) $fees->sum(fn ($line) => (float) $line->debit_usd))->toBe(15.0)
        ->and($fees->pluck('project_code')->unique()->values()->all())->toBe(['GEN-ALP-SYP'])
        ->and((float) $voucher->lines->sum(fn ($line) => (float) $line->credit_usd))->toBe(1005.0)
        ->and($built->difference)->toBe('-15.00');
});

it('refuses a local exchange with no rate, no pounds account or not enough dollars', function () {
    expect(localExchange(['rate' => ''])->problems)->not->toBe([])
        ->and(localExchange(['syp' => '1111'])->problems)->not->toBe([])
        ->and(localExchange(['amount' => '9000'])->problems)->not->toBe([])
        ->and(localExchange(['commission' => '-1'])->problems)->not->toBe([]);
});

it('lists the exchange differences booked on the general SYP account per city: the reserve report', function () {
    approveVoucher(app(PostVoucher::class)->post(localExchange(['received' => '14850000', 'commission' => '5'])->draft));
    // A gain in the same city: more pounds than dollars x rate.
    approveVoucher(app(PostVoucher::class)->post(localExchange(['amount' => '100', 'received' => '1515000'])->draft));
    // A 5503 line that is not a transfer's, and a pending voucher, stay out.
    ledgerApprovedVoucher(['type' => VoucherType::Journal], [
        new LineDraft('5503', 'USD', debit: 40, projectCode: 'GEN-ALP-SYP'),
        new LineDraft('1111', 'USD', credit: 40, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
    ]);
    app(PostVoucher::class)->post(localExchange(['received' => '14000000'])->draft);

    $report = app(FollowUpReports::class)->run('xreserve', new FollowUpFilters(to: '2026-12-31'));

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0])->toMatchArray(['city' => 'Aleppo', 'gains' => '1.00', 'losses' => '15.00', 'reserve' => '-14.00'])
        ->and($report['totals'])->toBe(['gains' => '1.00', 'losses' => '15.00', 'reserve' => '-14.00']);
});

it('warns when a voucher drives the general USD balance with the transfer company below zero, without blocking it', function () {
    $draft = ledgerReceipt(['type' => VoucherType::Transfer], [
        new LineDraft('1161', 'USD', credit: 300, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury, contact: 'Hawala Co'),
        new LineDraft('1111', 'USD', debit: 300, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
    ]);

    $warnings = app(VoucherWarnings::class)->for($draft);

    expect($warnings)->toHaveCount(1)->and($warnings[0])->toContain('GEN-ALP-USD')->toContain('-300.00')
        ->and(app(PostVoucher::class)->check($draft))->toBe([])
        ->and(app(PostVoucher::class)->post($draft))->toBeInstanceOf(Voucher::class);

    // With money in the pool the same credit is fine.
    ledgerApprovedVoucher(['type' => VoucherType::Receipt], [
        new LineDraft('1161', 'USD', debit: 1000, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury, contact: 'Hawala Co'),
        new LineDraft('4101', 'USD', credit: 1000, projectCode: 'GEN-ALP-USD'),
    ]);

    expect(app(VoucherWarnings::class)->for($draft))->toBe([]);
});

it('flags the months of the hawala reconciliation where a general account is negative', function () {
    approveVoucher(app(PostVoucher::class)->post(ledgerReceipt(['type' => VoucherType::Transfer, 'date' => CarbonImmutable::parse('2026-03-20')], [
        new LineDraft('1161', 'USD', credit: 300, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury, contact: 'Hawala Co'),
        new LineDraft('1111', 'USD', debit: 300, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
    ])));

    $rows = app(FollowUpReports::class)->run('recon', new FollowUpFilters(to: '2026-04-30'))['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['negative'])->toBe('GEN-ALP-USD')
        ->and($rows[1]['negative'])->toBe('GEN-ALP-USD');
});

it('opens the reserve screen for head office', function () {
    $user = User::query()->findOrFail(createFinanceUser('xr', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    $this->actingAs($user)->get(route(Navigation::routeName('xreserve')))->assertOk();
    Livewire::actingAs($user)->test(FollowUpReport::class, ['screen' => 'xreserve'])->call('export')->assertFileDownloaded('xreserve.csv');
});
