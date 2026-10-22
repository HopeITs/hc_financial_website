<?php

use App\Support\FinancePassword;
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
        $table->decimal('Approval_Limit', 18, 2)->default(0);
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
    (require database_path('migrations/2026_10_23_090000_create_gl_programs_tables.php'))->up();
    (require database_path('migrations/2026_10_23_100000_create_web_project_managers_table.php'))->up();
    (require database_path('migrations/2026_10_24_090000_create_gl_accounts_projects_budget_lines_tables.php'))->up();
    (require database_path('migrations/2026_10_24_100000_create_gl_voucher_tables.php'))->up();

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

function createFinanceUser(string $username, string $password, string $role = 'Head Office', int $roleId = 2): int
{
    DB::table('role')->insertOrIgnore(['ID' => $roleId, 'Name' => $role]);

    return DB::table('user')->insertGetId([
        'Username' => $username,
        'Password' => FinancePassword::make($password),
        'Role' => $roleId,
    ]);
}
