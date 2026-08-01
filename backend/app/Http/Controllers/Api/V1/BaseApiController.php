<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Base controller for every tenant-facing v1 API controller. Provides the
 * shared response helpers so controllers call $this->success(...) instead
 * of reaching for App\Support\ApiResponse directly, plus a consistent
 * per_page resolver so pagination size is capped the same way everywhere.
 */
abstract class BaseApiController extends Controller
{
    /**
     * The largest page size any endpoint will honour, regardless of what a
     * client requests via ?per_page=. Prevents a client from requesting
     * per_page=100000 and forcing a full table scan.
     */
    protected const MAX_PER_PAGE = 100;

    protected const DEFAULT_PER_PAGE = 20;

    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function noContent(): JsonResponse
    {
        return ApiResponse::noContent();
    }

    protected function paginated(
        LengthAwarePaginator|AnonymousResourceCollection $paginator,
        ?string $message = null,
    ): JsonResponse {
        return ApiResponse::paginated($paginator, $message);
    }

    protected function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
        ?string $errorCode = null,
    ): JsonResponse {
        return ApiResponse::error($message, $status, $errors, $errorCode);
    }

    /**
     * Resolves the requested page size from ?per_page=, clamped between 1
     * and self::MAX_PER_PAGE, falling back to self::DEFAULT_PER_PAGE when
     * absent or non-numeric.
     */
    protected function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min($requested, self::MAX_PER_PAGE));
    }
}
