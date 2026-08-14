<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied after `auth:super_admin` on every routes/api_mission.php route
 * that needs an authenticated caller. The guard alone only proves the
 * token belongs to *some* SuperAdmin — this middleware additionally
 * rejects a suspended account, which Sanctum's guard has no concept of.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $superAdmin = $request->user('super_admin');

        if (!$superAdmin instanceof SuperAdmin) {
            return ApiResponse::error(
                trans('common.unauthenticated'),
                Response::HTTP_UNAUTHORIZED,
                errorCode: 'unauthenticated',
            );
        }

        if (!$superAdmin->isActive()) {
            return ApiResponse::error(
                trans('common.mission.suspended'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'super_admin_suspended',
            );
        }

        return $next($request);
    }
}
