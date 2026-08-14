<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response('', 200)]);
    }

    public function test_forgot_password_sends_a_reset_link_for_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'owner@example.com']);

        $response->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_gives_the_same_generic_response_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk();
        $response->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
        Notification::assertNothingSent();
    }

    public function test_reset_password_with_a_valid_token_changes_the_password_and_revokes_existing_tokens(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('old-password')]);
        $user->createToken('device-a');
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'N3w!StrongPassw0rd',
            'password_confirmation' => 'N3w!StrongPassw0rd',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('N3w!StrongPassw0rd', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'owner@example.com',
            'password' => 'N3w!StrongPassw0rd',
            'password_confirmation' => 'N3w!StrongPassw0rd',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'password_reset_failed');
    }

    public function test_change_password_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'N3w!StrongPassw0rd',
            'new_password_confirmation' => 'N3w!StrongPassw0rd',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'invalid_current_password');
    }

    public function test_change_password_succeeds_and_keeps_the_current_session_but_revokes_others(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        $currentToken = $user->createToken('current')->plainTextToken;
        $user->createToken('other-device');

        $response = $this->withHeader('Authorization', "Bearer {$currentToken}")->postJson('/api/v1/auth/change-password', [
            'current_password' => 'correct-password',
            'new_password' => 'N3w!StrongPassw0rd',
            'new_password_confirmation' => 'N3w!StrongPassw0rd',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('N3w!StrongPassw0rd', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());

        // The token used to make this very request must still work.
        $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }
}
