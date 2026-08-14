<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_authenticated_user_and_their_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $user->tenant;
        $tenant->forceFill(['owner_id' => $user->id])->save();
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('data.user.email', $user->email);
        $response->assertJsonPath('data.user.is_tenant_owner', true);
        $response->assertJsonPath('data.tenant.id', $tenant->id);
        $response->assertJsonPath('data.plan', null);
        $response->assertJsonPath('data.limits', []);
        $response->assertJsonPath('data.permissions', []);
    }

    public function test_rejects_an_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
