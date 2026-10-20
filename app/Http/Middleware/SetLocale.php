<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the interface language for the request: the choice kept in the
 * session, else the application default. (The `user` table has no locale
 * column, so the choice is per browser session.)
 */
class SetLocale
{
    public const SessionKey = 'locale';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::tryFrom((string) $request->session()->get(self::SessionKey))
            ?? Locale::tryFrom((string) config('app.locale'))
            ?? Locale::English;

        app()->setLocale($locale->value);

        return $next($request);
    }
}
