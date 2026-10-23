<?php

namespace App\Providers;

use App\Contracts\Approvable;
use App\Contracts\ProjectBalanceSource;
use App\Enums\ScreenAction;
use App\Models\LedgerRequest;
use App\Models\User;
use App\Policies\ApprovalPolicy;
use App\Policies\RequestPolicy;
use App\Services\Projects\LegacyProjectBalanceSource;
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
        // Task 20 swaps this for the ledger implementation; the pages only know the interface.
        $this->app->bind(ProjectBalanceSource::class, LegacyProjectBalanceSource::class);
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
        Gate::policy(LedgerRequest::class, RequestPolicy::class);
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
