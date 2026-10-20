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
        $table->integer('id')->primary();
        $table->string('name');
    });

    Schema::create('user', function ($table) {
        $table->increments('id');
        $table->string('username');
        $table->string('password');
        $table->integer('role')->nullable();
    });

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

function createFinanceUser(string $username, string $password, string $role = 'Financial manager'): int
{
    DB::table('role')->insertOrIgnore(['id' => 1, 'name' => $role]);

    return DB::table('user')->insertGetId([
        'username' => $username,
        'password' => FinancePassword::make($password),
        'role' => 1,
    ]);
}
