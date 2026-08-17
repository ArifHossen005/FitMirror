<?php

namespace App\Services\Plan;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * Trial start and cancellation — the two pieces of Phase 3.B's lifecycle
 * buildable without Phase 3.C's SSLCommerz integration existing yet.
 * "Trial length" isn't actually global, it's per-plan (`plans.
 * trial_days`, matching the product document's "৭ বা ১৪ দিনের free trial"
 * note that Mission Control configures this). No route calls either
 * method yet: plan selection happens at checkout (Phase 3.E), which
 * doesn't exist. Built now, ahead of that caller, the same "primitive
 * first, wire up the real route later" pattern as `tenant.active`/
 * `tenant.2fa` in Phase 2.B.
 */
class SubscriptionService extends BaseService
{
    public function startTrial(Tenant $tenant, Plan $plan, BillingCycle $billingCycle = BillingCycle::Monthly): Subscription
    {
        // withoutTenantScope(): this service is handed $tenant explicitly
        // and has no need of (and may not have) an ambient TenantContext
        // matching it — the same reasoning as every other
        // BelongsToTenant-model lookup elsewhere in the app that already
        // knows which tenant it's acting on. Without this, TenantScope's
        // fail-closed default (Decision D-13) would silently find zero
        // rows here whenever this runs outside that tenant's own request
        // context, defeating the duplicate-trial check entirely.
        $hasActiveOrTrialing = Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [SubscriptionStatus::Trialing, SubscriptionStatus::Active])
            ->exists();

        if ($hasActiveOrTrialing) {
            throw ValidationException::withMessages([
                'tenant' => ['This tenant already has an active or trialing subscription.'],
            ]);
        }

        return $this->transaction(function () use ($tenant, $plan, $billingCycle) {
            $trialEndsAt = $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : now();

            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'status' => $plan->trial_days > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::PendingPayment,
                'starts_at' => now(),
                'trial_ends_at' => $plan->trial_days > 0 ? $trialEndsAt : null,
            ]);

            $tenant->forceFill([
                'plan_id' => $plan->id,
                'status' => $plan->trial_days > 0 ? TenantStatus::Trial : $tenant->status,
                'trial_ends_at' => $plan->trial_days > 0 ? $trialEndsAt : $tenant->trial_ends_at,
            ])->save();

            return $subscription;
        });
    }

    /**
     * The tenant's current trialing/active/past_due/grace subscription —
     * `withoutTenantScope()` for the same reason as `startTrial()` above.
     */
    public function currentFor(Tenant $tenant): ?Subscription
    {
        return Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [
                SubscriptionStatus::Trialing,
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Grace,
            ])
            ->latest('starts_at')
            ->first();
    }

    /**
     * `$immediately = true` transitions to Cancelled right now. `false`
     * ("at the end of the current period") only flips `auto_renew` off
     * and records the reason — the actual transition to Cancelled at
     * period end is Phase 3.B's still-unbuilt auto-renewal job's job to
     * make (it would see `auto_renew = false` at the subscription's
     * `ends_at`/`trial_ends_at` and simply not renew). Building that job
     * now would mean fabricating the renewal-date bookkeeping it depends
     * on ahead of a real caller; this method only records intent.
     */
    public function cancel(Subscription $subscription, ?string $reason, bool $immediately): Subscription
    {
        if ($immediately) {
            $subscription->transitionTo(SubscriptionStatus::Cancelled);
            $subscription->forceFill(['cancellation_reason' => $reason])->save();

            return $subscription;
        }

        $subscription->forceFill([
            'auto_renew' => false,
            'cancellation_reason' => $reason,
        ])->save();

        return $subscription;
    }
}
