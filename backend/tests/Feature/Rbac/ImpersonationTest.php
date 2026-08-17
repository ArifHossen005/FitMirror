<?php

namespace Tests\Feature\Rbac;

use App\Enums\SuperAdminRole;
use App\Models\Impersonation;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_start_an_impersonation_session_and_use_it_on_the_tenant_api(): void
    {
        $tenant = Tenant::factory()->create();
        $target = User::factory()->create(['tenant_id' => $tenant->id]);
        $target->assignRole('owner');

        $superAdmin = SuperAdmin::factory()->create();
        $superAdminToken = $superAdmin->createToken('mc')->plainTextToken;

        $start = $this->withHeader('Authorization', "Bearer {$superAdminToken}")
            ->postJson("/api/v1/mission/impersonate/{$target->id}", ['reason' => 'Support ticket #42']);

        $start->assertOk();
        $impersonationToken = $start->json('data.token');
        $this->assertNotEmpty($impersonationToken);

        $this->assertDatabaseHas('impersonations', [
            'super_admin_id' => $superAdmin->id,
            'user_id' => $target->id,
            'tenant_id' => $tenant->id,
        ]);

        $me = $this->withHeader('Authorization', "Bearer {$impersonationToken}")->getJson('/api/v1/auth/me');
        $me->assertOk();
        $me->assertJsonPath('data.user.id', $target->id);
    }

    public function test_finance_role_cannot_start_an_impersonation_session(): void
    {
        $tenant = Tenant::factory()->create();
        $target = User::factory()->create(['tenant_id' => $tenant->id]);

        $finance = SuperAdmin::factory()->role(SuperAdminRole::Finance)->create();
        $token = $finance->createToken('mc')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/mission/impersonate/{$target->id}");

        $response->assertForbidden();
    }

    public function test_exit_revokes_the_impersonation_token_and_closes_the_audit_row(): void
    {
        $tenant = Tenant::factory()->create();
        $target = User::factory()->create(['tenant_id' => $tenant->id]);
        $target->assignRole('staff');

        $superAdmin = SuperAdmin::factory()->create();
        $superAdminToken = $superAdmin->createToken('mc')->plainTextToken;

        $start = $this->withHeader('Authorization', "Bearer {$superAdminToken}")
            ->postJson("/api/v1/mission/impersonate/{$target->id}");
        $impersonationToken = $start->json('data.token');

        $exit = $this->withHeader('Authorization', "Bearer {$impersonationToken}")
            ->postJson('/api/v1/auth/impersonation/exit');
        $exit->assertNoContent();

        // Laravel's AuthManager caches guard instances (and Sanctum's
        // RequestGuard caches its resolved user on top of that) for the
        // container's lifetime — which, in a single test method making
        // sequential requests as different tokens, spans this whole test.
        // Production never hits this: every real HTTP request gets a fresh
        // container. forgetGuards() forces the next call below to
        // re-resolve against the token's *current* (now-deleted) state
        // instead of returning the exit call's stale cached resolution.
        Auth::forgetGuards();

        $again = $this->withHeader('Authorization', "Bearer {$impersonationToken}")->getJson('/api/v1/auth/me');
        $again->assertUnauthorized();

        $impersonation = Impersonation::query()->where('user_id', $target->id)->firstOrFail();
        $this->assertNotNull($impersonation->ended_at);
    }

    public function test_an_ordinary_session_token_cannot_call_the_exit_endpoint(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('auth', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/impersonation/exit');

        $response->assertForbidden();
    }
}
