<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\AssignSubdomainRequest;
use App\Services\Store\SubdomainService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The tenant's `{slug}.fitmirror.com` address.
 *
 * Both routes are behind `tenant_settings.update` rather than a store
 * permission — the subdomain is the whole account's address, not one
 * branch's, so a Manager (who has no tenant_settings grant by default, see
 * config/permissions.php) cannot change where the shop lives on the
 * internet.
 */
class SubdomainController extends BaseApiController
{
    public function __construct(private readonly SubdomainService $subdomains) {}

    /**
     * Live availability check as the owner types. Rate-limited by the
     * 'tenant' limiter attached to the route, since it is called on every
     * keystroke.
     */
    public function check(Request $request): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $validated = $request->validate([
            'subdomain' => ['required', 'string', 'max:' . SubdomainService::MAX_LENGTH],
        ]);

        return $this->success(
            $this->subdomains->check($validated['subdomain'], $request->user()->tenant),
        );
    }

    public function assign(AssignSubdomainRequest $request): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $result = $this->subdomains->assign($request->user()->tenant, $request->validated('subdomain'));

        return $this->success($result, 'Your FitMirror address has been updated.');
    }

    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        return $this->success([
            'subdomain' => $tenant->subdomain,
            'url' => $this->subdomains->urlFor((string) $tenant->subdomain),
            'custom_domain' => $tenant->custom_domain,
        ]);
    }

    /**
     * A plain permission check rather than a policy: there is no model
     * being acted on — the target is the tenant's own settings, and
     * TenantPolicy's existing methods are about Mission Control acting on
     * a tenant, not a tenant acting on itself.
     *
     * Throws AuthorizationException rather than abort(403) so
     * ApiExceptionRenderer emits the standard `unauthorized` error code,
     * matching every policy-gated route; a bare abort() would surface as a
     * generic `http_error` the dashboard has no case for.
     *
     * @throws AuthorizationException
     */
    private function authorizeTenantSettings(Request $request): void
    {
        if (!$request->user()->can('tenant_settings.update')) {
            throw new AuthorizationException;
        }
    }
}
