<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function fakeLegacyDatabase(?string $version): void
{
    config([
        'database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'finance.legacy_tables' => ['version', 'role'],
        'finance.legacy_views' => [],
    ]);
    DB::purge('legacy');

    $schema = Schema::connection('legacy');
    $schema->create('version', function ($table) {
        $table->integer('id');
        $table->string('l_version');
    });
    $schema->create('role', fn ($table) => $table->integer('id'));

    if ($version !== null) {
        DB::connection('legacy')->table('version')->insert(['id' => 1, 'l_version' => $version]);
    }
}

it('passes when the version matches and all tables exist', function () {
    fakeLegacyDatabase('3.1.1.5');

    $this->artisan('finance:check-db')->assertExitCode(0);
});

it('fails on a different legacy version', function () {
    fakeLegacyDatabase('3.1.1.4');

    $this->artisan('finance:check-db')->assertExitCode(1);
});

it('fails when a required table is missing', function () {
    fakeLegacyDatabase('3.1.1.5');
    config(['finance.legacy_tables' => ['version', 'role', 'donor']]);

    $this->artisan('finance:check-db')->assertExitCode(1);
});
