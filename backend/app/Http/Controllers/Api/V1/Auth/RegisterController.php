<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/register — shop-owner registration. Does not log the
 * new owner in (no token issued here): the tenant starts life as `pending`
 * and the owner's email is unverified, so the client is expected to route
 * to the "verify your email" screen next, not straight into the dashboard.
 */
class RegisterController extends BaseApiController
{
    public function __construct(private readonly RegistrationService $registrationService) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->registrationService->register(
            $request->validated(),
        );

        return $this->created(
            data: [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status->value,
                ],
                'user' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ],
            ],
            message: 'Account created. Please check your email to verify your address.',
        );
    }
}
