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

function createFinanceUser(string $username, string $password, string $role = 'Head Office', int $roleId = 2): int
{
    DB::table('role')->insertOrIgnore(['ID' => $roleId, 'Name' => $role]);

    return DB::table('user')->insertGetId([
        'Username' => $username,
        'Password' => FinancePassword::make($password),
        'Role' => $roleId,
    ]);
}
