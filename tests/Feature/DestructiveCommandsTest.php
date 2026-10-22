<?php

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\WipeCommand;

afterEach(function () {
    FreshCommand::prohibit(false);
    WipeCommand::prohibit(false);
});

function bootProviderWithDatabase(string $database): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
    ]);

    (new AppServiceProvider(app()))->boot();
}

it('prohibits destructive commands on hcsyria_ databases', function () {
    bootProviderWithDatabase('hcsyria_financial_test');

    $this->artisan('migrate:fresh')->assertExitCode(1);
    $this->artisan('db:wipe')->assertExitCode(1);
});

it('allows destructive commands on other databases', function () {
    bootProviderWithDatabase(':memory:');

    $this->artisan('migrate:fresh')->assertExitCode(0);
});
