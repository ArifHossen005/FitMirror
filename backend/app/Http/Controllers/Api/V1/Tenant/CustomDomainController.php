<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\CustomDomainRequestRequest;
use App\Models\CustomDomainRequest;
use App\Services\Store\CustomDomainService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Connecting the tenant's own domain, behind
 * `plan.feature:custom_domain` (Max only, per PlanSeeder) and the
 * `tenant_settings.update` permission — the domain is the whole account's
 * address, the same reasoning as SubdomainController.
 *
 * The response always carries the exact DNS record to publish
 * (CustomDomainRequest::dnsInstructions()) rather than leaving the
 * dashboard to reconstruct it, so what the tenant is told to create and
 * what the verifier actually looks for cannot drift apart.
 */
class CustomDomainController extends BaseApiController
{
    public function __construct(private readonly CustomDomainService $domains) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $domainRequest = $this->domains->current($request->user()->tenant);

        return $this->success([
            'request' => $domainRequest === null ? null : $this->present($domainRequest),
            'active_domain' => $request->user()->tenant->custom_domain,
        ]);
    }

    public function store(CustomDomainRequestRequest $request): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $domainRequest = $this->domains->request($request->user()->tenant, $request->validated('domain'));

        return $this->created(
            $this->present($domainRequest),
            'Domain saved. Add the DNS record below, then run verification.',
        );
    }

    /**
     * Resolves the TXT challenge. A record that has not propagated yet is a
     * normal, successful response reporting `verified: false` with the
     * reason — not an error — so the dashboard can poll this without
     * treating propagation delay as a failure.
     */
    public function verify(Request $request): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $domainRequest = $this->domains->current($request->user()->tenant);

        if ($domainRequest === null) {
            return $this->error(
                'Add a domain before running verification.',
                404,
                errorCode: 'custom_domain_not_requested',
            );
        }

        $verified = $this->domains->verify($domainRequest);

        return $this->success(
            $this->present($verified),
            $verified->status->isRetryable()
                ? 'Not verified yet. DNS changes can take up to 24 hours to propagate.'
                : 'Domain verified. Your shop is now reachable at this address.',
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $domainRequest = $this->domains->current($request->user()->tenant);

        if ($domainRequest !== null) {
            $this->domains->remove($domainRequest);
        }

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CustomDomainRequest $domainRequest): array
    {
        return [
            'id' => $domainRequest->id,
            'domain' => $domainRequest->domain,
            'status' => $domainRequest->status->value,
            'status_label' => $domainRequest->status->label(),
            'is_verified' => !$domainRequest->status->isRetryable(),
            'dns' => $domainRequest->dnsInstructions(),
            'attempts' => $domainRequest->attempts,
            'verified_at' => $domainRequest->verified_at?->toIso8601String(),
            'last_checked_at' => $domainRequest->last_checked_at?->toIso8601String(),
            'last_error' => $domainRequest->last_error,
        ];
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeTenantSettings(Request $request): void
    {
        if (!$request->user()->can('tenant_settings.update')) {
            throw new AuthorizationException;
        }
    }
}
