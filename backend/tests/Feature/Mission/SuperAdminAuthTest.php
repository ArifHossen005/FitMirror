<?php

namespace Tests\Feature\Mission;

use App\Enums\SuperAdminRole;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_degraded_when_no_super_admin_is_seeded(): void
    {
        $response = $this->getJson('/api/v1/mission/health');

        $response->assertStatus(503);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.checks.database', true);
        $response->assertJsonPath('data.checks.super_admin_seeded', false);
    }

    public function test_health_endpoint_is_healthy_once_a_super_admin_exists(): void
    {
        SuperAdmin::factory()->create();

        $response = $this->getJson('/api/v1/mission/health');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonPath('data.checks.super_admin_seeded', true);
    }

    public function test_me_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/v1/mission/me');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_me_endpoint_rejects_a_tenant_user_sanctum_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mission/me');

        $response->assertStatus(401);
    }

    public function test_me_endpoint_returns_the_authenticated_super_admin(): void
    {
        $superAdmin = SuperAdmin::factory()->role(SuperAdminRole::Finance)->create([
            'name' => 'Ayesha Rahman',
            'email' => 'ayesha@fitmirror.com',
        ]);
        $token = $superAdmin->createToken('mission-control')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mission/me');

        $response->assertOk();
        $response->assertJsonPath('data.email', 'ayesha@fitmirror.com');
        $response->assertJsonPath('data.role', 'finance');
        $response->assertJsonPath('data.permissions', ['billing', 'plans']);
    }

    public function test_me_endpoint_rejects_a_suspended_super_admin(): void
    {
        $superAdmin = SuperAdmin::factory()->suspended()->create();
        $token = $superAdmin->createToken('mission-control')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mission/me');

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'super_admin_suspended');
    }
}
