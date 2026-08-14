<?php

namespace Tests\Feature\Mission;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MissionLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_valid_credentials_and_issues_a_token(): void
    {
        $superAdmin = SuperAdmin::factory()->create([
            'email' => 'owner@fitmirror.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/mission/login', [
            'email' => 'owner@fitmirror.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.super_admin.email', 'owner@fitmirror.com');
        $response->assertJsonStructure(['data' => ['token']]);

        $this->assertNotNull($superAdmin->fresh()->last_login_at);
    }

    public function test_login_rejects_an_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/mission/login', [
            'email' => 'nobody@fitmirror.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'invalid_credentials');
    }

    public function test_login_rejects_an_incorrect_password(): void
    {
        SuperAdmin::factory()->create([
            'email' => 'owner@fitmirror.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/mission/login', [
            'email' => 'owner@fitmirror.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'invalid_credentials');
    }

    public function test_login_rejects_a_suspended_account(): void
    {
        SuperAdmin::factory()->suspended()->create([
            'email' => 'owner@fitmirror.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/mission/login', [
            'email' => 'owner@fitmirror.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'super_admin_suspended');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/mission/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $superAdmin = SuperAdmin::factory()->create();
        $token = $superAdmin->createToken('mission-control')->plainTextToken;

        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/mission/logout');
        $logoutResponse->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Laravel's AuthManager caches a resolved guard (and the user it
        // authenticated) for the lifetime of the container — real traffic
        // never hits this because every HTTP request gets a fresh
        // container, but two requests simulated in one test method share
        // one, so the guard must be forgotten to force it to re-evaluate
        // the (now-revoked) bearer token on the next call.
        Auth::forgetGuards();

        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mission/me');
        $meResponse->assertStatus(401);
    }
}
