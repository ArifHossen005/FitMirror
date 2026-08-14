<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabling_returns_a_secret_and_qr_code(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/enable');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_code_svg']]);
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_confirming_with_a_valid_code_enables_two_factor_and_returns_recovery_codes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $auth = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $auth()->postJson('/api/v1/auth/2fa/enable');
        $secret = $user->fresh()->two_factor_secret;
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $auth()->postJson('/api/v1/auth/2fa/confirm', ['code' => $code]);

        $response->assertOk();
        $recoveryCodes = $response->json('data.recovery_codes');
        $this->assertCount(8, $recoveryCodes);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirming_with_an_invalid_code_fails(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $auth = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $auth()->postJson('/api/v1/auth/2fa/enable');

        $response = $auth()->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000']);

        $response->assertStatus(422);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disabling_clears_the_secret_and_recovery_codes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $auth = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $auth()->postJson('/api/v1/auth/2fa/enable');
        $code = app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
        $auth()->postJson('/api/v1/auth/2fa/confirm', ['code' => $code]);

        $response = $auth()->postJson('/api/v1/auth/2fa/disable');

        $response->assertOk();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_regenerating_recovery_codes_replaces_the_old_ones(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $auth = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $auth()->postJson('/api/v1/auth/2fa/enable');
        $code = app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
        $confirmResponse = $auth()->postJson('/api/v1/auth/2fa/confirm', ['code' => $code]);
        $originalCodes = $confirmResponse->json('data.recovery_codes');

        $response = $auth()->postJson('/api/v1/auth/2fa/recovery-codes');

        $response->assertOk();
        $newCodes = $response->json('data.recovery_codes');
        $this->assertCount(8, $newCodes);
        $this->assertNotSame($originalCodes, $newCodes);
    }

    public function test_recovery_code_can_complete_a_login_challenge_exactly_once(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);
        $token = $user->createToken('t')->plainTextToken;
        $auth = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $auth()->postJson('/api/v1/auth/2fa/enable');
        $code = app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
        $confirmResponse = $auth()->postJson('/api/v1/auth/2fa/confirm', ['code' => $code]);
        $recoveryCode = $confirmResponse->json('data.recovery_codes.0');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);
        $challengeToken = $loginResponse->json('data.two_factor_token');

        $first = $this->postJson('/api/v1/auth/2fa/challenge', [
            'two_factor_token' => $challengeToken,
            'recovery_code' => $recoveryCode,
        ]);
        $first->assertOk();

        // The same recovery code, reused, must fail — one-time use only.
        // A fresh login is needed for a second challenge token since the
        // first was consumed by completing the challenge above.
        $secondLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-password',
        ]);

        $second = $this->postJson('/api/v1/auth/2fa/challenge', [
            'two_factor_token' => $secondLoginResponse->json('data.two_factor_token'),
            'recovery_code' => $recoveryCode,
        ]);
        $second->assertStatus(422);
    }

    public function test_two_factor_secret_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/2fa/enable');

        $decryptedSecret = $user->fresh()->two_factor_secret;
        $rawColumnValue = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        // The `encrypted` cast (App\Models\User::casts()) means the raw
        // column never contains the plaintext TOTP secret — only Laravel's
        // encrypted payload does, which `Crypt::decryptString()` reverses.
        $this->assertNotNull($rawColumnValue);
        $this->assertNotSame($decryptedSecret, $rawColumnValue);
        $this->assertSame($decryptedSecret, Crypt::decryptString($rawColumnValue));
    }
}
