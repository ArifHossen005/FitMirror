<?php

namespace App\Http\Controllers\Mission;

use App\Models\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * GET /api/v1/mission/health — unauthenticated liveness probe for the
 * Mission Control API surface, mirroring Api\V1\HealthController. Kept
 * separate (rather than reusing the tenant health endpoint) since Mission
 * Control is deployed/monitored as its own product surface and adds one
 * check the tenant API has no reason to know about: whether a bootstrap
 * super admin actually exists, so a fresh deploy that forgot to run the
 * seeder is visible immediately instead of surfacing as a mysterious
 * "can't log in" report later.
 */
class MissionHealthController extends BaseMissionController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => true,
            'database' => $this->checkDatabase(),
            'super_admin_seeded' => $this->checkSuperAdminSeeded(),
        ];

        $healthy = !in_array(false, $checks, true);

        return $this->success(
            data: [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            message: $healthy ? 'Mission Control API is healthy.' : 'Mission Control API is degraded.',
            status: $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkSuperAdminSeeded(): bool
    {
        try {
            return SuperAdmin::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
