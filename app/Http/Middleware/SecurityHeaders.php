<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the browser-hardening response headers to every response.
 *
 * The CSP keeps 'unsafe-inline' and 'unsafe-eval' for scripts because Livewire
 * and Alpine need them; everything else is locked to this origin, which still
 * blocks framing, plugins, base-tag and form hijacking, and any request to a
 * third-party host. HSTS is sent only over HTTPS in production, so a local
 * http:// copy can never be locked out.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->contentSecurityPolicy($request),
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // PHP adds X-Powered-By itself (expose_php), outside the response object.
        $response->headers->remove('X-Powered-By');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $devServer = $this->viteDevServer();
        $webSocket = $devServer === null ? null : preg_replace('/^http/', 'ws', $devServer);

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => array_filter(["'self'", "'unsafe-inline'", "'unsafe-eval'", $devServer]),
            'style-src' => array_filter(["'self'", "'unsafe-inline'", $devServer]),
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => array_filter(["'self'", 'data:', $devServer]),
            'connect-src' => array_filter(["'self'", $devServer, $webSocket]),
            'frame-src' => ["'self'"],
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');

        return $request->isSecure() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /**
     * The Vite dev server origin while `npm run dev` is running, else null.
     */
    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $origin = trim((string) @file_get_contents(Vite::hotFile()));

        return $origin === '' ? null : $origin;
    }
}
