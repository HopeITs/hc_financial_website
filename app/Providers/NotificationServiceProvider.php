<?php

namespace App\Providers;

use App\Models\RequestAudit;
use App\Models\VoucherAudit;
use App\Services\Notifications\Notifier;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Sends the bell's notifications (U-01) from the audit rows of vouchers and requests, once the change is committed:
 * a rolled-back approval notifies no one, and a failure here is reported but never undoes the approval.
 */
class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        VoucherAudit::created(fn (VoucherAudit $audit) => $this->afterCommit(fn (Notifier $notifier) => $notifier->voucherAudited($audit)));
        RequestAudit::created(fn (RequestAudit $audit) => $this->afterCommit(fn (Notifier $notifier) => $notifier->requestAudited($audit)));
    }

    private function afterCommit(Closure $send): void
    {
        DB::afterCommit(function () use ($send): void {
            try {
                $this->app->call($send);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
