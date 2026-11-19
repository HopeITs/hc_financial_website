<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\FinancialStatements;
use App\Models\User;
use App\Services\Ledger\FinancialStatements as Statements;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    seedChartWithTestProjects();
});

function stmtVoucher(string $date, array $lines, bool $approve = true): void
{
    $voucher = app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse($date)], $lines));

    if ($approve) {
        DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);
    }
}

function stmtReceipt(string $date, int $amount, bool $approve = true): void
{
    stmtVoucher($date, [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4001', 'USD', credit: $amount, projectCode: 'F2F'),
    ], $approve);
}

it('balances the position, folding the unclosed result into net assets', function () {
    stmtReceipt('2026-01-05', 100);
    stmtReceipt('2026-02-01', 50, approve: false);

    $data = app(Statements::class)->position('2026-06-30');
    $assets = $data['sections'][0];
    $netAssets = $data['sections'][2];

    expect($assets['key'])->toBe('assets')
        ->and($assets['lines'][0]['label'])->toBe('Cash and cash equivalents')
        ->and($assets['current'])->toBe('100.00')
        ->and($netAssets['current'])->toBe('100.00')
        ->and($netAssets['lines'][0]['label'])->toBe('__unclosed_result__')
        ->and($data['liabilities_and_net_assets']['current'])->toBe('100.00')
        ->and($data['balanced'])->toBeTrue();
});

it('reports income, expenses and the surplus of a period, with 5503 under financial and banking costs', function () {
    stmtReceipt('2026-01-05', 100);
    stmtVoucher('2026-02-10', [
        new LineDraft('5503', 'USD', debit: 30, projectCode: 'F2F'),
        new LineDraft('1111', 'USD', credit: 30, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);
    stmtReceipt('2025-03-01', 999);

    $data = app(Statements::class)->activities('2026-01-01', '2026-12-31');
    [$income, $expenses] = $data['sections'];

    expect($income['current'])->toBe('100.00')
        ->and($expenses['lines'][0]['label'])->toBe('Financial and banking costs')
        ->and($expenses['current'])->toBe('30.00')
        ->and($data['surplus']['current'])->toBe('70.00')
        ->and($data['surplus']['previous'])->toBeNull();
});

it('shows a comparative period and keeps a line that only has a previous amount', function () {
    stmtReceipt('2025-03-01', 80);
    stmtReceipt('2026-03-01', 20);

    $data = app(Statements::class)->activities('2026-01-01', '2026-12-31', '2025-01-01', '2025-12-31', compare: true);

    expect($data['sections'][0]['current'])->toBe('20.00')
        ->and($data['sections'][0]['previous'])->toBe('80.00')
        ->and($data['surplus']['previous'])->toBe('80.00');

    $position = app(Statements::class)->position('2026-12-31', '2025-12-31');

    expect($position['sections'][0]['previous'])->toBe('80.00')
        ->and($position['sections'][0]['current'])->toBe('100.00');
});

it('converts only USD in the SYP view', function () {
    stmtReceipt('2026-01-05', 100);

    $data = app(Statements::class)->position('2026-06-30', null, 'SYP', '15000');

    expect($data['sections'][0]['current'])->toBe('1500000.00')
        ->and($data['balanced'])->toBeTrue();
});

it('opens for a signed-in user and downloads XLSX and PDF', function () {
    stmtReceipt('2026-01-05', 100);
    $user = User::query()->findOrFail(createFinanceUser('stmtuser', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    $this->actingAs($user)->get('/reports/financial-statements')->assertOk()->assertSee('Cash and cash equivalents');

    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);
        Livewire::actingAs($user)->test(FinancialStatements::class)
            ->call('export', 'xlsx')->assertFileDownloaded('financial-statements.xlsx')
            ->call('export', 'pdf')->assertFileDownloaded('financial-statements.pdf');
    }
});

it('shows euros kept in the bank at the day\'s rate, the gap as an unrealised exchange difference, and still balances', function () {
    stmtVoucher('2026-10-20', [
        new LineDraft('1176', 'EUR', debit: 1000, rate: 1.15, debitUsd: 1150),
        new LineDraft('4001', 'EUR', credit: 1000, rate: 1.15, creditUsd: 1150, projectCode: 'F2F'),
    ]);
    $manager = User::query()->findOrFail(createFinanceUser('Fadi', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value));
    app(App\Services\Treasury\DailyRates::class)->propose('EUR', '2026-10-31', '1.17', null, $manager, approveNow: true);

    $line = fn (array $statement, string $key, string $label): ?string => collect(collect($statement['sections'])->firstWhere('key', $key)['lines'])->firstWhere('label', $label)['current'] ?? null;
    $position = app(Statements::class)->position('2026-10-31');
    $october = app(Statements::class)->activities('2026-10-01', '2026-10-31');

    // 1,000 EUR booked at 1,150 $ show at the day's 1.17: 1,170 $, and 20 $ of exchange gain not yet realised.
    expect($line($position, 'assets', 'Cash and cash equivalents'))->toBe('1170.00')
        ->and($position['balanced'])->toBeTrue()
        ->and($line($october, 'expenses', 'Financial and banking costs'))->toBe('-20.00')
        ->and($october['surplus']['current'])->toBe('1170.00')
        ->and($line(app(Statements::class)->activities('2026-11-01', '2026-11-30'), 'expenses', 'Financial and banking costs'))->toBeNull();
});
