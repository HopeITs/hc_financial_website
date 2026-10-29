<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Support\UserPreferences;
use Illuminate\Auth\Events\Login;
use Throwable;

/**
 * At sign-in, the language the person chose last comes back (U-12). Someone who never chose one keeps the language
 * they picked on the sign-in page, and it is saved for next time. A failure never blocks a login.
 */
class ApplySavedLocale
{
    public function __construct(private readonly UserPreferences $preferences) {}

    public function handle(Login $event): void
    {
        if (! app()->bound('request') || ! request()->hasSession()) {
            return;
        }

        try {
            $userId = (int) $event->user->getAuthIdentifier();
            $session = request()->session();
            $saved = $this->preferences->locale($userId);

            if ($saved !== null) {
                $session->put(SetLocale::SessionKey, $saved->value);
            } elseif (($picked = Locale::tryFrom((string) $session->get(SetLocale::SessionKey))) !== null) {
                $this->preferences->setLocale($userId, $picked);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
