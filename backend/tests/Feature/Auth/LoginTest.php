<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'authenticated');
        $response->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_an_incorrect_password_and_records_the_attempt(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, LoginAttempt::query()->where('email', 'owner@example.com')->where('successful', false)->count());
    }

    public function test_login_rejects_a_suspended_user(): void
    {
        User::factory()->status(UserStatus::Suspended)->create([
            'email' => 'owner@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_account_locks_out_after_five_consecutive_failures(): void
    {
        // This test's own 6 rapid requests would otherwise collide with
        // the `throttle:auth` route middleware (5/min by ip+email,
        // AppServiceProvider::configureRateLimiters) before ever reaching
        // the progressive-lockout logic this test actually targets — the
        // two are independent defenses tested independently.
        $this->withoutMiddleware();

        User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong'])
                ->assertStatus(422);
        }

        // The 6th attempt uses the CORRECT password but must still be
        // rejected — the account is locked, not just the wrong guess.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['email' => ['Too many failed attempts. Please try again later.']]);
    }

    public function test_login_with_two_factor_enabled_returns_a_challenge_instead_of_a_token(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);
        app(TwoFactorService::class)->startSetup($user);
        $code = app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
        app(TwoFactorService::class)->confirm($user->fresh(), $code);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'two_factor_required');
        $response->assertJsonStructure(['data' => ['two_factor_token']]);
    }

    public function test_two_factor_challenge_completes_login_with_a_valid_code(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);
        $google2fa = app(Google2FA::class);
        app(TwoFactorService::class)->startSetup($user);
        app(TwoFactorService::class)->confirm($user->fresh(), $google2fa->getCurrentOtp($user->fresh()->two_factor_secret));

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);
        $challengeToken = $loginResponse->json('data.two_factor_token');

        $response = $this->postJson('/api/v1/auth/2fa/challenge', [
            'two_factor_token' => $challengeToken,
            'code' => $google2fa->getCurrentOtp($user->fresh()->two_factor_secret),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'authenticated');
        $response->assertJsonStructure(['data' => ['token']]);
    }

    public function test_two_factor_challenge_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);
        $google2fa = app(Google2FA::class);
        app(TwoFactorService::class)->startSetup($user);
        app(TwoFactorService::class)->confirm($user->fresh(), $google2fa->getCurrentOtp($user->fresh()->two_factor_secret));

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/v1/auth/2fa/challenge', [
            'two_factor_token' => $loginResponse->json('data.two_factor_token'),
            'code' => '000000',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('device-a')->plainTextToken;
        $user->createToken('device-b');

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(204);

        $this->assertSame(1, $user->tokens()->count());
    }
}
