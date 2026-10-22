<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->prohibitDestructiveCommandsOnLegacyDatabase();
    }

    /**
     * The `hcsyria_*` databases belong to the Windows program, so
     * migrate:fresh, migrate:refresh, migrate:reset and db:wipe must refuse.
     */
    protected function prohibitDestructiveCommandsOnLegacyDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        DB::prohibitDestructiveCommands(str_starts_with($database, 'hcsyria_'));
    }
}
