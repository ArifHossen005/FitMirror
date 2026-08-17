<?php

namespace Tests\Feature\Plan;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plan\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelSubscriptionEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The literal tenant owner (Tenant::owner_id), required by
     * CancelSubscriptionController's own authorization check — which
     * means EnsureTwoFactorIsEnabled ('tenant.2fa', see this route's
     * group in routes/api_v1.php) also applies, so 2FA must be enabled
     * here too. This is the first real business route to exercise that
     * requirement for an actual owner end-to-end (every prior 2FA test
     * used an ad-hoc route — see TwoFactorEnforcementTest's own docblock).
     */
    /**
     * @return array{0: Tenant, 1: User, 2: Subscription}
     */
    private function ownerWithTrial(): array
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $owner->assignRole('owner');
        $tenant->forceFill(['owner_id' => $owner->id])->save();
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $subscription = app(SubscriptionService::class)->startTrial($tenant, $pro);

        return [$tenant, $owner, $subscription];
    }

    public function test_the_owner_can_cancel_immediately(): void
    {
        [, $owner, $subscription] = $this->ownerWithTrial();
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/subscription/cancel', [
            'immediately' => true,
            'reason' => 'No longer needed',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
    }

    public function test_the_owner_can_schedule_cancellation_at_period_end(): void
    {
        [, $owner, $subscription] = $this->ownerWithTrial();
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/subscription/cancel', [
            'immediately' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'trialing');
        $response->assertJsonPath('data.auto_renew', false);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->fresh()->status);
    }

    public function test_a_non_owner_staff_member_cannot_cancel(): void
    {
        [$tenant] = $this->ownerWithTrial();
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');
        $token = $manager->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/subscription/cancel', [
            'immediately' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_cancelling_with_no_subscription_returns_a_clear_404(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $owner->assignRole('owner');
        $tenant->forceFill(['owner_id' => $owner->id])->save();
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/subscription/cancel', [
            'immediately' => true,
        ]);

        $response->assertNotFound();
        $response->assertJsonPath('error_code', 'no_active_subscription');
    }
}
