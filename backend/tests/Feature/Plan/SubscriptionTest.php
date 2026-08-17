<?php

namespace Tests\Feature\Plan;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Plan\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_trial_puts_the_tenant_in_trial_status_with_the_plans_own_trial_length(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail(); // trial_days = 14

        $subscription = app(SubscriptionService::class)->startTrial($tenant, $pro);

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame($pro->id, $subscription->plan_id);
        $this->assertTrue($subscription->trial_ends_at->isBetween(now()->addDays(13), now()->addDays(15)));

        $tenant->refresh();
        $this->assertSame(TenantStatus::Trial, $tenant->status);
        $this->assertSame($pro->id, $tenant->plan_id);
    }

    public function test_a_plan_with_no_trial_days_starts_pending_payment_instead(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $free = Plan::query()->where('slug', 'free')->firstOrFail(); // trial_days = 0

        $subscription = app(SubscriptionService::class)->startTrial($tenant, $free);

        $this->assertSame(SubscriptionStatus::PendingPayment, $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_a_tenant_cannot_start_a_second_trial_while_one_is_active(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $max = Plan::query()->where('slug', 'max')->firstOrFail();
        app(SubscriptionService::class)->startTrial($tenant, $pro);

        $this->expectException(ValidationException::class);

        app(SubscriptionService::class)->startTrial($tenant, $max);
    }

    public function test_valid_transitions_are_allowed_and_recorded(): void
    {
        $subscription = Subscription::factory()->status(SubscriptionStatus::Trialing)->create();

        $subscription->transitionTo(SubscriptionStatus::Active);

        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_cancelling_records_a_cancelled_at_timestamp(): void
    {
        $subscription = Subscription::factory()->status(SubscriptionStatus::Active)->create();

        $subscription->transitionTo(SubscriptionStatus::Cancelled);

        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_an_invalid_transition_throws_and_changes_nothing(): void
    {
        $subscription = Subscription::factory()->status(SubscriptionStatus::Trialing)->create();

        $this->expectException(RuntimeException::class);

        try {
            $subscription->transitionTo(SubscriptionStatus::Grace);
        } finally {
            $this->assertSame(SubscriptionStatus::Trialing, $subscription->fresh()->status);
        }
    }

    public function test_current_price_reads_from_the_plan_for_the_billing_cycle(): void
    {
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $subscription = Subscription::factory()->for($pro, 'plan')->create();

        $this->assertSame($pro->price_monthly, $subscription->currentPrice());
    }

    public function test_immediate_cancellation_transitions_to_cancelled_and_records_the_reason(): void
    {
        $subscription = Subscription::factory()->status(SubscriptionStatus::Active)->create();

        app(SubscriptionService::class)->cancel($subscription, 'Too expensive', immediately: true);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame('Too expensive', $subscription->cancellation_reason);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_end_of_period_cancellation_only_disables_auto_renew(): void
    {
        $subscription = Subscription::factory()->status(SubscriptionStatus::Active)->create(['auto_renew' => true]);

        app(SubscriptionService::class)->cancel($subscription, 'Switching plans', immediately: false);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertFalse($subscription->auto_renew);
        $this->assertSame('Switching plans', $subscription->cancellation_reason);
        $this->assertNull($subscription->cancelled_at);
    }

    public function test_current_for_finds_the_tenants_live_subscription_across_tenant_scope(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $subscription = app(SubscriptionService::class)->startTrial($tenant, $pro);

        $found = app(SubscriptionService::class)->currentFor($tenant);

        $this->assertSame($subscription->id, $found?->id);
    }
}
