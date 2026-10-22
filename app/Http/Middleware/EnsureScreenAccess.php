<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * `screen:<slug>[,<action>]` runs the `screen` gate (config/permissions.php) for the signed-in user.
 * Without a slug it uses the route's `{screen}` parameter. It is what `can:screen,<slug>` would be,
 * but `can:` reads its arguments as route parameters, so a fixed slug needs this alias.
 */
class EnsureScreenAccess
{
    public function handle(Request $request, Closure $next, ?string $slug = null, string $action = 'view'): Response
    {
        $slug ??= (string) $request->route('screen');

        Gate::authorize('screen', [$slug, $action]);

        return $next($request);
    }
}
