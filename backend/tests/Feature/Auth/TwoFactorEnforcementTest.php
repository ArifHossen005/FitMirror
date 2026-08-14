<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * No real business route uses `tenant.2fa` yet (Phase 2.B ships before any
 * such route exists) — same pattern as
 * tests/Feature/Tenancy/ResolveTenantMiddlewareTest.php, an ad-hoc route
 * proves the middleware itself works ahead of a real consumer.
 */
class TwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'tenant.2fa'])->get('/api/v1/__test/owner-only', fn () => response()->json(['ok' => true]));
    }

    public function test_a_tenant_owner_without_two_factor_is_blocked(): void
    {
        $owner = User::factory()->create();
        Tenant::query()->whereKey($owner->tenant_id)->update(['owner_id' => $owner->id]);
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/__test/owner-only');

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'two_factor_setup_required');
    }

    public function test_a_non_owner_staff_user_is_not_blocked(): void
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/__test/owner-only');

        $response->assertOk();
    }
}
