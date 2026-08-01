<?php

namespace App\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every FitMirror form request extends this instead of Laravel's
 * FormRequest directly, so a failed validation returns the standard API
 * error envelope (DOCUMENTATION.md §7.1) instead of a redirect — the app is
 * API-only and has no session-based forms to redirect back to.
 */
abstract class BaseFormRequest extends FormRequest
{
    /**
     * Authorization defaults to true — most endpoints gate access via route
     * middleware and policies, not per-request `authorize()` checks. Override
     * in a concrete request when the authorization decision genuinely depends
     * on the request's own input (e.g. comparing a route-model tenant_id).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                trans('common.validation_failed'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->toArray(),
                errorCode: 'validation_failed',
            ),
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                trans('common.unauthorized'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'unauthorized',
            ),
        );
    }
}
