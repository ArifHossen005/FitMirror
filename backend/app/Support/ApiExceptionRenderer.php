<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps every exception that reaches the API boundary to the FitMirror JSON
 * error envelope (DOCUMENTATION.md §7.1), wired in bootstrap/app.php via
 * withExceptions()->render(). Laravel 12 has no app/Exceptions/Handler.php —
 * this class is the equivalent for the API surface.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::validation($e),
            $e instanceof AuthenticationException => ApiResponse::error(
                trans('common.unauthenticated'),
                Response::HTTP_UNAUTHORIZED,
                errorCode: 'unauthenticated',
            ),
            $e instanceof AuthorizationException => ApiResponse::error(
                trans('common.unauthorized'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'unauthorized',
            ),
            $e instanceof ModelNotFoundException => ApiResponse::error(
                self::modelNotFoundMessage($e),
                Response::HTTP_NOT_FOUND,
                errorCode: 'not_found',
            ),
            $e instanceof NotFoundHttpException => ApiResponse::error(
                trans('common.not_found', ['item' => 'Resource']),
                Response::HTTP_NOT_FOUND,
                errorCode: 'not_found',
            ),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS => ApiResponse::error(
                trans('common.too_many_requests'),
                Response::HTTP_TOO_MANY_REQUESTS,
                errorCode: 'throttled',
            ),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : trans('common.error'),
                $e->getStatusCode(),
                errorCode: 'http_error',
            ),
            default => self::unexpected($e),
        };
    }

    protected static function validation(ValidationException $e): JsonResponse
    {
        return ApiResponse::error(
            trans('common.validation_failed'),
            $e->status,
            $e->errors(),
            errorCode: 'validation_failed',
        );
    }

    protected static function modelNotFoundMessage(ModelNotFoundException $e): string
    {
        $model = class_basename($e->getModel());

        return trans('common.not_found', ['item' => $model]);
    }

    protected static function unexpected(Throwable $e): JsonResponse
    {
        if (app()->hasDebugModeEnabled()) {
            return ApiResponse::error(
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                errors: [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->take(10)->toArray(),
                ],
                errorCode: 'server_error',
            );
        }

        return ApiResponse::error(
            trans('common.server_error'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            errorCode: 'server_error',
        );
    }
}
