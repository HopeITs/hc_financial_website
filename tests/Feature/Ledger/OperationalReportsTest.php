<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\OperationalReport;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\OperationalReports;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\ReportFilters;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function opVoucher(array $lines, string $date = '2026-03-10', bool $approve = true): string
{
    $voucher = app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse($date)], $lines));
    if ($approve) {
        DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);
    }

    return $voucher->id;
}

function opFund(string $project, int $amount): void
{
    opVoucher([
        new LineDraft('1111', 'USD', debit: $amount, projectCode: $project, fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: $project),
    ]);
}

/** Spends on the first USD budget line of SZ (an expense account needs its budget line and a center). */
function opSpend(int $amount, string $date = '2026-03-15', bool $approve = true): string
{
    $line = GlBudgetLine::query()->where('project_code', 'SZ')->where('currency', 'USD')->firstOrFail();

    return opVoucher([
        new LineDraft($line->account_code, 'USD', debit: $amount, projectCode: 'SZ', budgetLineKey: $line->key, center: 'Aleppo'),
        new LineDraft('1111', 'USD', credit: $amount, projectCode: 'SZ', fundSource: FundSource::DonorGrant),
    ], $date, $approve);
}

it('lists approved expense lines only and filters by project and date', function () {
    opFund('SZ', 500);
    opSpend(40, '2026-03-15');
    opSpend(25, '2026-04-20');
    opSpend(999, approve: false);

    $report = app(OperationalReports::class);

    expect($report->run('rexp')['rows'])->toHaveCount(2)
        ->and($report->run('rexp')['totals']['amount'])->toBe('65.00')
        ->and($report->run('rexp', ReportFilters::from(['from' => '2026-04-01']))['totals']['amount'])->toBe('25.00')
        ->and($report->run('rexp', ReportFilters::from(['project' => 'F2F']))['rows'])->toBe([]);
});

it('converts a dollar expense to lira at the typed rate', function () {
    opFund('SZ', 500);
    opSpend(10);

    expect(app(OperationalReports::class)->run('rexp', null, 'SYP', '15000')['totals']['amount'])->toBe('150000.00');
});

it('shows cash balances per account and currency up to a date', function () {
    opFund('SZ', 500);
    opSpend(40, '2026-03-15');

    $report = app(OperationalReports::class);
    $early = $report->run('rcash', ReportFilters::from(['to' => '2026-03-12']))['rows'];

    expect($early)->toHaveCount(1)
        ->and($early[0]['currency'])->toBe('USD')
        ->and($early[0]['amount'])->toBe('500.00')
        ->and($report->run('rcash')['rows'][0]['amount'])->toBe('460.00');
});

it('lists every budget line with its actual and what is left', function () {
    $line = GlBudgetLine::query()->where('project_code', 'SZ')->where('currency', 'USD')->firstOrFail();
    opFund('SZ', 500);

    opVoucher([
        new LineDraft($line->account_code, 'USD', debit: 30, projectCode: $line->project_code, budgetLineKey: $line->key, center: 'Aleppo'),
        new LineDraft('1111', 'USD', credit: 30, projectCode: $line->project_code, fundSource: FundSource::DonorGrant),
    ]);

    $row = collect(app(OperationalReports::class)->run('rbl', ReportFilters::from(['project' => $line->project_code]))['rows'])->firstWhere('name', $line->name);

    expect($row['actual'])->toBe('30.00')
        ->and($row['remaining'])->toBe(bcsub(number_format((float) $line->budget, 2, '.', ''), '30.00', 2));
});

it('reports income lines as revenue', function () {
    opFund('F2F', 500);
    opFund('SZ', 70);

    $data = app(OperationalReports::class)->run('rrev', ReportFilters::from(['project' => 'SZ']));

    expect($data['totals']['amount'])->toBe('70.00')
        ->and(collect($data['rows'])->where('kind', 'revenue'))->toHaveCount(1);
});

it('sums fixed asset accounts', function () {
    $asset = DB::table('gl_accounts')->where('statement_line', 'Property and equipment, net')->where('level', 'posting')->where('class', 'assets')->first();
    expect($asset)->not->toBeNull();
    opFund('F2F', 500);
    opVoucher([
        new LineDraft($asset->code, 'USD', debit: 120, projectCode: 'F2F', center: 'Aleppo'),
        new LineDraft('1111', 'USD', credit: 120, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);

    expect(app(OperationalReports::class)->run('rfixed')['totals']['amount'])->toBe('120.00');
});

it('lists attachments of approved vouchers with the line filters', function () {
    opFund('SZ', 500);
    $voucher = opSpend(40);
    $hidden = opSpend(5, approve: false);
    foreach ([$voucher, $hidden] as $id) {
        DB::table('gl_voucher_attachments')->insert(['voucher_id' => $id, 'original_name' => $id.'.pdf', 'stored_path' => 'x/'.$id.'.pdf', 'size' => 10, 'created_at' => now(), 'updated_at' => now()]);
    }

    $report = app(OperationalReports::class);

    expect($report->run('rattach')['rows'])->toHaveCount(1)
        ->and($report->run('rattach')['rows'][0]['file'])->toBe($voucher.'.pdf')
        ->and($report->run('rattach', ReportFilters::from(['project' => 'F2F']))['rows'])->toBe([]);
});

it('serves each report screen and rejects unknown ones', function () {
    $user = User::query()->findOrFail(createFinanceUser('opuser', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    foreach (OperationalReports::REPORTS as $slug) {
        $this->actingAs($user)->get(route(Navigation::routeName($slug)))->assertOk();
    }

    Livewire::actingAs($user)->test(OperationalReport::class, ['screen' => 'rexp'])->call('export')->assertFileDownloaded('rexp.csv');
    Livewire::actingAs($user)->test(OperationalReport::class, ['screen' => 'nope'])->assertNotFound();
});
