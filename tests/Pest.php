<?php

use App\Enums\FundSource;
use App\Enums\VoucherSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Voucher;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use App\Support\FinancePassword;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * The tables of the Windows program's database that the web app touches, in the
 * in-memory test database. The real ones come from the finance database itself.
 */
function createFinanceSchema(): void
{
    Schema::create('role', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
    });

    Schema::create('user', function ($table) {
        $table->increments('ID');
        $table->string('Username');
        $table->string('Password');
        $table->integer('Role')->nullable();
        $table->integer('State')->default(1);
    });

    (require database_path('migrations/2026_10_22_120000_create_web_user_roles_table.php'))->up();
    (require database_path('migrations/2026_10_22_130000_create_web_user_preferences_table.php'))->up();
    (require database_path('migrations/2026_10_31_090000_create_web_user_permissions_table.php'))->up();
    (require database_path('migrations/2026_10_23_090000_create_gl_programs_tables.php'))->up();
    (require database_path('migrations/2026_10_23_100000_create_web_project_managers_table.php'))->up();
    (require database_path('migrations/2026_10_24_090000_create_gl_accounts_projects_budget_lines_tables.php'))->up();
    (require database_path('migrations/2026_10_24_100000_create_gl_voucher_tables.php'))->up();
    (require database_path('migrations/2026_10_27_090000_create_gl_micro_loans_tables.php'))->up();
    (require database_path('migrations/2026_10_28_090000_add_beneficiary_details_to_gl_micro_loans.php'))->up();
    (require database_path('migrations/2026_10_29_090000_create_web_assistant_tables.php'))->up();
    (require database_path('migrations/2026_10_30_090000_create_gl_currencies_table.php'))->up();
    (require database_path('migrations/2026_10_31_110000_create_web_form_drafts_table.php'))->up();

    Schema::create('log', function ($table) {
        $table->increments('id');
        $table->string('form_name');
        $table->string('title');
        $table->string('affected_table');
        $table->string('affected_id');
        $table->string('details');
        $table->string('additionaldetails');
        $table->string('query');
        $table->date('date');
        $table->string('user');
    });
}

/**
 * The `legacy` connection as an in-memory database holding the two views the home page reads
 * (`cash_balances`, `tree_view`) and the programs page reads (`project`, `subproject`, `account`) as plain tables, with the columns the page uses.
 */
function createLegacyHomeSchema(): void
{
    if (config('database.connections.legacy.driver') !== 'sqlite') {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
    }

    Schema::connection('legacy')->create('cash_balances', function ($table) {
        $table->integer('CashAccount_id');
        $table->string('CashAccount_Name')->nullable();
        $table->integer('City_ID')->nullable();
        $table->string('City_Name')->nullable();
        $table->string('Subproject_Name')->nullable();
        $table->integer('Account_ID');
        $table->string('Account_Name');
        $table->string('Account_Type')->default('Project');
        $table->integer('account_IsClosed')->default(0);
        $table->double('Account_Net')->default(0);
        $table->double('Account_Net_USD')->default(0);
        $table->double('Account_Net_New')->default(0);
    });

    Schema::connection('legacy')->create('project', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
    });

    Schema::connection('legacy')->create('subproject', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
        $table->integer('Project_ID');
        $table->integer('City_ID')->nullable();
    });

    Schema::connection('legacy')->create('account', function ($table) {
        $table->integer('ID')->primary();
        $table->integer('SubProject_ID');
        $table->string('Name')->nullable();
        $table->integer('IsClosed')->default(0);
        $table->integer('city_id')->nullable();
        $table->string('BeginDate')->nullable();
        $table->string('EndDate')->nullable();
        $table->integer('Accounttype_id')->nullable();
    });

    Schema::connection('legacy')->create('accounttype', function ($table) {
        $table->integer('id')->primary();
        $table->string('name');
    });

    Schema::connection('legacy')->create('user_accounts', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->integer('subproject_id')->nullable();
        $table->integer('account_id')->nullable();
    });

    Schema::connection('legacy')->create('user_cashaccounts', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->integer('cashaccount_id');
        $table->integer('Iscashier')->nullable();
    });

    Schema::connection('legacy')->create('city', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
    });

    Schema::connection('legacy')->create('donor', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
    });

    Schema::connection('legacy')->create('account_donor', function ($table) {
        $table->integer('Account_id');
        $table->integer('Donor_id');
    });

    Schema::connection('legacy')->create('budget', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
        $table->integer('Account_ID');
    });

    Schema::connection('legacy')->create('line', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
    });

    Schema::connection('legacy')->create('budget_line', function ($table) {
        $table->integer('ID')->primary();
        $table->integer('Budget_ID');
        $table->integer('Line_ID');
        $table->double('Unit')->default(0);
        $table->double('Timing')->default(0);
        $table->double('Price')->default(0);
    });

    Schema::connection('legacy')->create('tilltypes', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
    });

    Schema::connection('legacy')->create('till', function ($table) {
        $table->integer('ID')->primary();
        $table->integer('Type_ID');
        $table->integer('Account_ID');
    });

    Schema::connection('legacy')->create('action', function ($table) {
        $table->integer('ID')->primary();
        $table->integer('Till_ID');
        $table->integer('AType_ID');
        $table->double('Amount');
        $table->string('ActionDate');
        $table->integer('IsApproved')->default(1);
        $table->string('deleted_at')->nullable();
    });

    Schema::connection('legacy')->create('tree_view', function ($table) {
        $table->integer('CityID')->nullable();
        $table->string('CityName')->nullable();
        $table->string('ProjectName')->nullable();
        $table->string('SubprojectName')->nullable();
        $table->integer('AccountID')->nullable();
        $table->string('AccountName')->nullable();
        $table->integer('IsClosed')->nullable();
    });
}

/**
 * The tables the read-only master data screens read from the `legacy` connection, with the columns they use. Call it after
 * createLegacyHomeSchema(), which has the donor, city, till type and account tables this adds columns to; whatever is
 * there already is left alone.
 */
function createMasterDataSchema(): void
{
    $legacy = Schema::connection('legacy');

    if (! $legacy->hasColumn('donor', 'Abb_Name')) {
        $legacy->table('donor', fn ($table) => $table->string('Abb_Name')->nullable());
    }

    if (! $legacy->hasColumn('tilltypes', 'is_local_currency')) {
        $legacy->table('tilltypes', function ($table) {
            $table->integer('is_local_currency')->default(0);
            $table->decimal('base_divisor', 18, 6)->default(1);
        });
    }

    $create = function (string $name, callable $columns) use ($legacy) {
        if (! $legacy->hasTable($name)) {
            $legacy->create($name, $columns);
        }
    };

    $create('cashaccount', function ($table) {
        $table->integer('id')->primary();
        $table->string('Name');
        $table->integer('currency_tilltype_id')->nullable();
        $table->integer('city_id')->nullable();
    });
    $create('denominations', function ($table) {
        $table->integer('id')->primary();
        $table->string('name');
        $table->integer('currency_tilltype_id')->nullable();
    });
    $create('employee', function ($table) {
        $table->integer('id')->primary();
        $table->string('FirstName');
        $table->string('LastName')->nullable();
        $table->string('FatherName')->nullable();
        $table->string('BeginDate')->nullable();
        $table->string('EndDate')->nullable();
    });
    $create('fiscalbond', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
        $table->string('Date')->nullable();
        $table->string('Notes')->nullable();
        $table->integer('Account_ID')->nullable();
        $table->integer('Till_ID')->nullable();
    });
    $create('linecategorytype', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
    });
    $create('linecategory', function ($table) {
        $table->integer('ID')->primary();
        $table->string('Name');
        $table->integer('Type_ID')->nullable();
        $table->integer('Kind')->nullable();
    });
}

/** The ledger tables of the web app with the real chart of accounts, projects and budget lines seeded. */
function createLedgerSchema(): void
{
    (require database_path('migrations/2026_10_24_090000_create_gl_accounts_projects_budget_lines_tables.php'))->up();
    (require database_path('migrations/2026_10_24_100000_create_gl_voucher_tables.php'))->up();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_10_30_090000_create_gl_currencies_table.php'))->up();
    (new ChartOfAccountsSeeder)->run();
}

/** A balanced, valid receipt: 100 USD into the Aleppo USD safe from the ACN grant, project F2F. */
function ledgerReceipt(array $header = [], ?array $lines = null): VoucherDraft
{
    $lines ??= [
        new LineDraft('1111', 'USD', debit: 100, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 100, projectCode: 'F2F'),
    ];

    return new VoucherDraft(
        type: $header['type'] ?? VoucherType::Receipt,
        date: $header['date'] ?? CarbonImmutable::parse('2026-03-10'),
        preparedBy: $header['preparedBy'] ?? 5,
        lines: $lines,
        description: $header['description'] ?? 'ACN grant',
        source: $header['source'] ?? VoucherSource::Web,
        legacyActionId: $header['legacyActionId'] ?? null,
        requestId: $header['requestId'] ?? null,
    );
}

/** Posts a voucher and approves it, as the financial manager (user 9) would. */
function ledgerApprovedVoucher(array $header = [], ?array $lines = null): Voucher
{
    $voucher = app(PostVoucher::class)->post(ledgerReceipt($header, $lines));
    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => 9, 'approved_at' => now()]);

    return $voucher->refresh();
}

function createFinanceUser(string $username, string $password, string $role = 'Head Office', int $roleId = 2): int
{
    DB::table('role')->insertOrIgnore(['ID' => $roleId, 'Name' => $role]);

    return DB::table('user')->insertGetId([
        'Username' => $username,
        'Password' => FinancePassword::make($password),
        'Role' => $roleId,
    ]);
}
