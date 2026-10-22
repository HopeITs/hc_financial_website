<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes the same `log` row the Windows program writes at login, so sign-ins
 * from both programs show on the Log screen. A log failure never blocks a login.
 */
class LogWebLogin
{
    public function handle(Login $event): void
    {
        try {
            DB::table('log')->insert([
                'form_name' => 'Web_Login',
                'title' => 'LOGIN',
                'affected_table' => '',
                'affected_id' => '',
                'details' => '',
                'additionaldetails' => '',
                'query' => '',
                'date' => now()->toDateString(),
                'user' => $event->user instanceof User ? $event->user->username : '',
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
