<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * The billing-relationship state machine — see SubscriptionStatus's own
 * docblock for how this differs from (and is expected to eventually drive)
 * TenantStatus. No route creates or reads these yet; Phase 3.C's payment
 * webhooks and Phase 3.E's checkout flow are the first real callers.
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'billing_cycle',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'grace_ends_at',
        'cancelled_at',
        'cancellation_reason',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function canTransitionTo(SubscriptionStatus $target): bool
    {
        return in_array($target, $this->status->allowedNextStates(), true);
    }

    /**
     * Moves to $target, recording cancelled_at when the target is
     * Cancelled — the one status-specific side effect every transition
     * needs, so callers don't have to remember it themselves.
     *
     * @throws RuntimeException if the transition isn't in
     *                          SubscriptionStatus::allowedNextStates() for the current status.
     */
    public function transitionTo(SubscriptionStatus $target): void
    {
        if (!$this->canTransitionTo($target)) {
            throw new RuntimeException(
                "Cannot transition subscription #{$this->id} from {$this->status->value} to {$target->value}.",
            );
        }

        $attributes = ['status' => $target];

        if ($target === SubscriptionStatus::Cancelled) {
            $attributes['cancelled_at'] = now();
        }

        $this->forceFill($attributes)->save();
    }

    /**
     * The price for this subscription's billing_cycle, in the smallest
     * whole-taka unit the Plan itself is priced in (see Plan's own
     * docblock) — reads live from the related Plan, not a snapshot on
     * this row, since a plan price change should apply at the next
     * renewal, not retroactively to a stored subscription value that
     * doesn't exist here by design.
     */
    public function currentPrice(): int
    {
        return $this->billing_cycle === BillingCycle::Yearly
            ? $this->plan->price_yearly
            : $this->plan->price_monthly;
    }
}
