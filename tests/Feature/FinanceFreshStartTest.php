<?php

use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/*
 * Starting the books again (finance request of 2026-11-30): only the chart of accounts and the users stay.
 */

beforeEach(function () {
    createFinanceSchema();
    seedChartWithTestProjects();
    $this->userId = createFinanceUser('Ann', 'secret pass', 'Accountant', 4);
    giveProjects($this->userId, ['F2F', 'GEN-ALP-USD']);
    ledgerApprovedVoucher();
    $this->accounts = GlAccount::query()->count();
});

it('only counts what would go when not forced', function () {
    $this->artisan('finance:fresh-start')->assertExitCode(0);

    expect(Voucher::query()->count())->toBe(1)
        ->and(GlProject::query()->where('code', 'F2F')->exists())->toBeTrue();
});

it('deletes nothing when the database name is not typed back', function () {
    $this->artisan('finance:fresh-start', ['--force' => true])
        ->expectsQuestion('Type the database name ('.DB::connection()->getDatabaseName().') to delete these rows', 'no')
        ->assertExitCode(1);

    expect(Voucher::query()->count())->toBe(1);
});

it('keeps only the chart of accounts, the general accounts and the users', function () {
    $name = DB::connection()->getDatabaseName();
    $this->artisan('finance:fresh-start', ['--force' => true])
        ->expectsQuestion("Type the database name ({$name}) to delete these rows", $name)
        ->assertExitCode(0);

    expect(Voucher::query()->count())->toBe(0)
        ->and(DB::table('gl_voucher_lines')->count())->toBe(0)
        ->and(GlProject::query()->where('is_general_account', false)->count())->toBe(0)
        ->and(GlProject::query()->where('code', 'GEN-ALP-USD')->exists())->toBeTrue()
        ->and(DB::table('gl_budget_lines')->where('project_code', 'F2F')->count())->toBe(0)
        ->and(GlAccount::query()->count())->toBe($this->accounts)
        ->and(DB::table('user')->where('ID', $this->userId)->exists())->toBeTrue()
        ->and(DB::table('web_user_projects')->where('user_id', $this->userId)->pluck('project_code')->all())->toBe(['GEN-ALP-USD']);
});
