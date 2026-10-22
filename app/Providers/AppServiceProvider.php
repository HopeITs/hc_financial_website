<?php

namespace App\Providers;

use App\Contracts\Approvable;
use App\Enums\ScreenAction;
use App\Models\User;
use App\Policies\ApprovalPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $this->defineAuthorization();
    }

    /**
     * The `screen:<slug>` route middleware and `can:screen` check screens; `Gate::allows('approve', $voucher)` guards an approval.
     */
    protected function defineAuthorization(): void
    {
        Gate::define('screen', fn (User $user, string $slug, string $action = 'view'): bool => ($permitted = ScreenAction::tryFrom($action)) !== null
            && $user->canOnScreen($slug, $permitted));

        Gate::policy(Approvable::class, ApprovalPolicy::class);
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
