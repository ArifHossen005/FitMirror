<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Trialing,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'auto_renew' => true,
        ];
    }

    public function status(SubscriptionStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
