<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * GET /api/v1/health — unauthenticated liveness/readiness probe used by
 * uptime monitors, load balancer health checks, and local verification
 * (DOCUMENTATION.md §3.11). Reports each dependency independently so a
 * degraded Redis instance, for example, doesn't hide behind a generic 500.
 */
class HealthController extends BaseApiController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => true,
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = !in_array(false, $checks, true);

        return $this->success(
            data: [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            message: $healthy ? 'FitMirror API is healthy.' : 'FitMirror API is degraded.',
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

    private function checkRedis(): bool
    {
        try {
            return Redis::connection()->ping() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Confirms the queue connection is reachable. This does not prove a
     * worker is actively consuming it — per Decision D-01 the local worker
     * runs as `queue:work`, not Horizon, so there is no supervisor process
     * to introspect from inside the request lifecycle on Windows.
     */
    private function checkQueue(): bool
    {
        try {
            Redis::connection('queue')->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
