<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale in priority order:
 *   1. Authenticated user's saved `locale` preference (Phase 2.B `users.locale`)
 *   2. `Accept-Language` request header
 *   3. config('app.locale') fallback (bn)
 *
 * Only `bn` and `en` are supported — anything else falls through to the
 * config default rather than erroring, since a client sending an
 * unsupported tag (e.g. a browser default of `hi-IN`) shouldn't break the
 * request.
 */
class ResolveLocale
{
    private const SUPPORTED_LOCALES = ['bn', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveFromUser($request)
            ?? $this->resolveFromHeader($request)
            ?? config('app.locale');

        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveFromUser(Request $request): ?string
    {
        $user = $request->user();

        if ($user === null || !isset($user->locale)) {
            return null;
        }

        return in_array($user->locale, self::SUPPORTED_LOCALES, true) ? $user->locale : null;
    }

    private function resolveFromHeader(Request $request): ?string
    {
        $header = $request->header('Accept-Language');

        if (empty($header)) {
            return null;
        }

        foreach (explode(',', $header) as $tag) {
            $primary = strtolower(trim(explode(';', $tag)[0]));
            $primary = explode('-', $primary)[0];

            if (in_array($primary, self::SUPPORTED_LOCALES, true)) {
                return $primary;
            }
        }

        return null;
    }
}
