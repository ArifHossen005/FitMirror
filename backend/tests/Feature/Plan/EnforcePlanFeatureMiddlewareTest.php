<?php

namespace Tests\Feature\Plan;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * No real route attaches `plan.feature` yet (see the middleware's own
 * docblock — the first real caller lands with Phase 7's campaign routes),
 * so this registers a throwaway route the same way
 * TenantScopeIsolationTest uses a throwaway fixture model.
 */
class EnforcePlanFeatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 'api' first — ResolveTenant/ResolveLocale (prepended to that
        // group in bootstrap/app.php) must run before 'tenant.active' can
        // find a resolved tenant to check. A route registered without it
        // never gets TenantContext populated at all, so it 404s from
        // EnsureTenantIsActive itself rather than exercising plan.feature.
        Route::middleware(['api', 'auth:sanctum', 'tenant.active', 'plan.feature:campaign_manager'])
            ->get('/api/v1/_test/campaign-gated', fn () => response()->json(['ok' => true]));
    }

    public function test_a_free_plan_tenant_is_blocked_with_an_upgrade_cta(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/_test/campaign-gated');

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'plan_feature_unavailable');
        $response->assertJsonPath('errors.feature', 'campaign_manager');
        $this->assertNotEmpty($response->json('errors.upgrade_url'));
    }

    public function test_a_pro_plan_tenant_passes_through(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/_test/campaign-gated');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
    }
}
