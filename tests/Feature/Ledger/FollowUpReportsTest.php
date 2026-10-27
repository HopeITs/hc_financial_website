<?php

use App\Enums\RoleId;
use App\Livewire\FollowUpReport;
use App\Models\User;
use App\Services\Ledger\FollowUpFilters;
use App\Services\Ledger\FollowUpReports;
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

/**
 * Writes an approved (or other status) voucher straight into the ledger tables: these reports read lines, not posting rules.
 *
 * @param  list<array<string, mixed>>  $lines  account, debit/credit (USD), project, contact, city, uncomp
 */
function followVoucher(string $id, string $date, array $lines, string $status = 'approved'): void
{
    DB::table('gl_vouchers')->insert(['id' => $id, 'type' => 'JV', 'date' => $date, 'status' => $status, 'prepared_by' => 5, 'created_at' => now(), 'updated_at' => now()]);
    foreach ($lines as $index => $line) {
        DB::table('gl_voucher_lines')->insert([
            'voucher_id' => $id, 'line_no' => $index + 1, 'account_code' => $line['account'], 'currency' => 'USD',
            'debit_orig' => $line['debit'] ?? 0, 'credit_orig' => $line['credit'] ?? 0, 'debit_usd' => $line['debit'] ?? 0, 'credit_usd' => $line['credit'] ?? 0,
            'project_code' => $line['project'] ?? null, 'contact' => $line['contact'] ?? null, 'city' => $line['city'] ?? null,
            'is_uncompensated' => $line['uncomp'] ?? false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

it('reconciles the transfer company pool with project and general balances, month by month', function () {
    followVoucher('R-1', '2026-01-10', [['account' => '1161', 'debit' => 1000, 'project' => 'SZ'], ['account' => '1111', 'credit' => 1000]]);
    followVoucher('R-2', '2026-02-05', [['account' => '1161', 'debit' => 300, 'project' => 'GEN-ALP-USD'], ['account' => '1111', 'credit' => 300]]);
    followVoucher('R-3', '2026-02-20', [['account' => '1161', 'debit' => 50], ['account' => '1111', 'credit' => 50]]);
    followVoucher('R-4', '2026-02-25', [['account' => '1161', 'credit' => 400, 'project' => 'SZ'], ['account' => '1111', 'debit' => 400]]);
    followVoucher('R-5', '2026-02-26', [['account' => '1161', 'debit' => 9999, 'project' => 'SZ']], 'pending_approval');

    $rows = app(FollowUpReports::class)->run('recon', new FollowUpFilters(to: '2026-03-31'))['rows'];

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toMatchArray(['month' => '2026-01', 'pool' => '1000.00', 'projects' => '1000.00', 'general' => '0.00', 'difference' => '0.00'])
        ->and($rows[1])->toMatchArray(['month' => '2026-02', 'pool' => '950.00', 'projects' => '600.00', 'general' => '300.00', 'difference' => '50.00'])
        ->and($rows[2])->toMatchArray(['month' => '2026-03', 'pool' => '950.00', 'difference' => '50.00']);

    $city = app(FollowUpReports::class)->run('recon', new FollowUpFilters(to: '2026-02-28', project: 'GEN-ALP-USD'))['rows'];
    expect($city)->toHaveCount(1)->and($city[0])->toMatchArray(['pool' => '300.00', 'general' => '300.00']);
});

it('buckets uncompensated cash per city by age', function () {
    followVoucher('U-1', '2026-03-20', [['account' => '1111', 'debit' => 100, 'city' => 'Aleppo', 'uncomp' => true]]);
    followVoucher('U-2', '2026-02-20', [['account' => '1111', 'debit' => 200, 'city' => 'Aleppo', 'uncomp' => true]]);
    followVoucher('U-3', '2025-12-01', [['account' => '1111', 'debit' => 400, 'city' => 'Aleppo', 'uncomp' => true]]);
    followVoucher('U-4', '2026-03-25', [['account' => '1111', 'debit' => 50, 'city' => 'Homs', 'uncomp' => true]]);
    followVoucher('U-5', '2026-03-25', [['account' => '1111', 'debit' => 70, 'city' => 'Homs']]);
    followVoucher('U-6', '2026-03-25', [['account' => '1111', 'debit' => 900, 'city' => 'Homs', 'uncomp' => true]], 'draft');

    $report = app(FollowUpReports::class)->run('uncomp', new FollowUpFilters(to: '2026-04-01'), CarbonImmutable::parse('2026-06-01'));

    expect($report['rows'])->toBe([
        ['city' => 'Aleppo', 'under_30' => '100.00', 'days_30_90' => '200.00', 'over_90' => '400.00', 'total' => '700.00'],
        ['city' => 'Homs', 'under_30' => '50.00', 'days_30_90' => '0.00', 'over_90' => '0.00', 'total' => '50.00'],
    ])->and($report['totals'])->toBe(['under_30' => '150.00', 'days_30_90' => '200.00', 'over_90' => '400.00', 'total' => '750.00']);
});

it('lists open advances by person with no ageing and keeps a settlement difference on the person', function () {
    followVoucher('A-1', '2026-01-05', [['account' => '1201', 'debit' => 500, 'contact' => 'Rana'], ['account' => '1111', 'credit' => 500]]);
    followVoucher('A-2', '2026-02-05', [['account' => '1201', 'credit' => 200, 'contact' => 'Rana'], ['account' => '5101', 'debit' => 200]]);
    followVoucher('A-3', '2026-01-06', [['account' => '1201', 'debit' => 100, 'contact' => 'Omar']]);
    followVoucher('A-4', '2026-01-20', [['account' => '1201', 'credit' => 130, 'contact' => 'Omar']]);
    followVoucher('A-5', '2026-01-07', [['account' => '1201', 'debit' => 80, 'contact' => 'Sami']]);
    followVoucher('A-6', '2026-01-21', [['account' => '1201', 'credit' => 80, 'contact' => 'Sami']]);
    followVoucher('A-7', '2026-01-08', [['account' => '1201', 'debit' => 999, 'contact' => 'Lina']], 'rejected');

    $report = app(FollowUpReports::class)->run('advances');

    expect(array_column($report['rows'], 'open', 'person'))->toBe(['Omar' => '-30.00', 'Rana' => '300.00'])
        ->and($report['totals'])->toBe(['advanced' => '600.00', 'settled' => '330.00', 'open' => '270.00']);
});

it('reports micro loan collection and arrears, counting only approved payments', function () {
    DB::table('gl_micro_loans')->insert([
        ['id' => 1, 'beneficiary' => 'Hana', 'project_code' => 'SZ', 'loan_amount' => 600, 'grant_amount' => 200, 'currency' => 'USD'],
        ['id' => 2, 'beneficiary' => 'Karim', 'project_code' => 'F2F', 'loan_amount' => 300, 'grant_amount' => 0, 'currency' => 'USD'],
    ]);
    followVoucher('P-1', '2026-02-10', [['account' => '1111', 'debit' => 100, 'project' => 'SZ']]);
    followVoucher('P-2', '2026-03-10', [['account' => '1111', 'debit' => 100, 'project' => 'SZ']], 'pending_approval');
    DB::table('gl_micro_instalments')->insert([
        ['loan_id' => 1, 'due_date' => '2026-02-01', 'amount' => 100, 'paid_amount' => 100, 'paid_voucher_id' => 'P-1'],
        ['loan_id' => 1, 'due_date' => '2026-03-01', 'amount' => 100, 'paid_amount' => 100, 'paid_voucher_id' => 'P-2'],
        ['loan_id' => 1, 'due_date' => '2026-06-01', 'amount' => 100, 'paid_amount' => 0, 'paid_voucher_id' => null],
        ['loan_id' => 2, 'due_date' => '2026-01-01', 'amount' => 50, 'paid_amount' => 0, 'paid_voucher_id' => null],
    ]);

    $report = app(FollowUpReports::class)->run('loans', new FollowUpFilters(to: '2026-04-01'));

    expect($report['rows'])->toHaveCount(2)
        ->and($report['rows'][0])->toMatchArray(['beneficiary' => 'Karim', 'due' => '50.00', 'collected' => '0.00', 'arrears' => '50.00'])
        ->and($report['rows'][1])->toMatchArray(['beneficiary' => 'Hana', 'loan' => '600.00', 'grant' => '200.00', 'due' => '200.00', 'collected' => '100.00', 'arrears' => '100.00'])
        ->and($report['totals'])->toBe(['loan' => '900.00', 'grant' => '200.00', 'due' => '250.00', 'collected' => '100.00', 'arrears' => '150.00']);

    $only = app(FollowUpReports::class)->run('loans', new FollowUpFilters(to: '2026-04-01', project: 'SZ'));
    expect($only['rows'])->toHaveCount(1);
});

it('limits a project manager to their own projects', function () {
    createLegacyHomeSchema();
    $sz = 4321;
    DB::table('gl_projects')->where('code', 'SZ')->update(['legacy_account_id' => $sz]);

    followVoucher('M-1', '2026-01-05', [['account' => '1201', 'debit' => 100, 'contact' => 'Rana', 'project' => 'SZ']]);
    followVoucher('M-2', '2026-01-05', [['account' => '1201', 'debit' => 70, 'contact' => 'Omar', 'project' => 'F2F']]);

    $id = createFinanceUser('pm', 'secret pass', RoleId::ProjectManager->name, RoleId::ProjectManager->value);
    DB::table('web_project_managers')->insert(['user_id' => $id, 'account_id' => $sz]);
    $manager = User::query()->findOrFail($id);

    $component = Livewire::actingAs($manager)->test(FollowUpReport::class, ['screen' => 'advances']);
    $component->assertSee('Rana')->assertDontSee('Omar');
    $this->actingAs($manager)->get(route('reports.uncompensated-cash'))->assertForbidden();
});

it('opens the four screens for head office and exports csv', function () {
    $user = User::query()->findOrFail(createFinanceUser('fuser', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    foreach (FollowUpReports::REPORTS as $slug) {
        $this->actingAs($user)->get(route(Navigation::routeName($slug)))->assertOk();
        Livewire::actingAs($user)->test(FollowUpReport::class, ['screen' => $slug])->call('export')->assertFileDownloaded($slug.'.csv');
    }

    Livewire::actingAs($user)->test(FollowUpReport::class, ['screen' => 'nope'])->assertNotFound();
});
