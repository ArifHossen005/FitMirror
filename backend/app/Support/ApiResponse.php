<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every API response FitMirror sends — success, error, or paginated — goes
 * through this class so the JSON envelope shape is identical everywhere.
 * See DOCUMENTATION.md §7.1 for the three envelope shapes this produces.
 */
class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $headers = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message ?? trans('common.success'),
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status, $headers);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public static function paginated(
        LengthAwarePaginator|AnonymousResourceCollection $paginator,
        ?string $message = null,
    ): JsonResponse {
        $resource = $paginator instanceof AnonymousResourceCollection
            ? $paginator->resource
            : $paginator;

        return response()->json([
            'success' => true,
            'message' => $message ?? trans('common.success'),
            'data' => $paginator instanceof AnonymousResourceCollection
                ? $paginator->response()->getData(true)['data']
                : $resource->items(),
            'meta' => [
                'current_page' => $resource->currentPage(),
                'per_page' => $resource->perPage(),
                'total' => $resource->total(),
                'last_page' => $resource->lastPage(),
                'from' => $resource->firstItem(),
                'to' => $resource->lastItem(),
            ],
        ], Response::HTTP_OK);
    }

    public static function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        ?array $errors = null,
        ?string $errorCode = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }

        return response()->json($payload, $status);
    }
}
