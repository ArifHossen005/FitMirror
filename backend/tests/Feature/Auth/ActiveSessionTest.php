<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_every_active_session_and_flags_the_current_one(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('this-device')->plainTextToken;
        $user->createToken('other-device');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/sessions');

        $response->assertOk();
        $sessions = $response->json('data');
        $this->assertCount(2, $sessions);
        $this->assertCount(1, array_filter($sessions, fn ($s) => $s['is_current']));
    }

    public function test_revoking_another_session_does_not_affect_the_current_one(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('this-device')->plainTextToken;
        $otherTokenId = $user->createToken('other-device')->accessToken->id;

        $response = $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->deleteJson("/api/v1/auth/sessions/{$otherTokenId}");

        $response->assertStatus(204);
        $this->assertSame(1, $user->tokens()->count());

        $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_cannot_revoke_a_session_belonging_to_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tokenA = $userA->createToken('a')->plainTextToken;
        $tokenBId = $userB->createToken('b')->accessToken->id;

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->deleteJson("/api/v1/auth/sessions/{$tokenBId}");

        $response->assertStatus(404);
        $this->assertSame(1, $userB->tokens()->count());
    }
}
