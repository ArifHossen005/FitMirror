<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drops any auth guard instance left over from a previous request in the
 * same PHP process, so `$request->user()` always resolves against *this*
 * request's credentials.
 *
 * This is the other half of Decision D-16. That decision fixed
 * ResolveTenant by giving it a stateless PersonalAccessToken lookup with
 * no guard to go stale — but every controller behind `auth:sanctum` still
 * calls `$request->user()`, and that path was left broken:
 *
 *   - `AuthManager::guard()` memoises each guard for the container's
 *     lifetime (`$this->guards[$name] ??= ...`).
 *   - Sanctum re-injects the current Request into its `RequestGuard` on
 *     every rebinding (`app()->refresh('request', $guard, 'setRequest')`),
 *     but `RequestGuard::user()` short-circuits on its own cached
 *     `$this->user`, which `setRequest()` never clears.
 *
 * The consequence, measured rather than assumed: two sequential requests
 * carrying two different bearer tokens both resolved to the *first*
 * token's user. `GET /auth/me` returned the wrong account outright, and
 * `GET /staff` returned an empty list — the controller filtered on the
 * stale user's `tenant_id` while TenantScope correctly filtered on the
 * real one, so the two conditions contradicted each other.
 *
 * D-16 called this class of failure "testing-only", on the grounds that
 * production runs one request per process (Decision D-01). That holds for
 * php-fpm today, but it is an assumption about the runtime rather than a
 * property of the code — anything that reuses a container across requests
 * (Octane, RoadRunner, a future long-lived worker) would leak one user's
 * session into the next user's request, which is a cross-tenant data leak,
 * not a test artefact. Forgetting the guards costs one array reset per
 * request and removes the assumption entirely.
 */
class ForgetStaleAuthGuards
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards();

        return $next($request);
    }
}
