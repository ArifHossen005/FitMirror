<?php

namespace App\Models;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide, not BelongsToTenant — the same code is redeemable by any
 * eligible tenant (like Plan, not like Subscription). Redemption limits
 * are enforced by App\Services\Billing\CouponService against
 * CouponRedemption rows, not by anything on this model itself.
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'applies_to_plans',
        'max_redemptions',
        'per_tenant_limit',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'applies_to_plans' => 'array',
            'max_redemptions' => 'integer',
            'per_tenant_limit' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => CouponStatus::class,
        ];
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function appliesToPlan(Plan $plan): bool
    {
        return $this->applies_to_plans === null || in_array($plan->slug, $this->applies_to_plans, true);
    }

    public function isWithinWindow(): bool
    {
        $now = now();

        if ($this->starts_at && $now->lessThan($this->starts_at)) {
            return false;
        }

        return !($this->expires_at && $now->greaterThan($this->expires_at));
    }

    /**
     * Discount for a $subtotal, capped at $subtotal so a coupon can never
     * make an invoice's discount exceed its subtotal (a negative total).
     */
    public function discountFor(int $subtotal): int
    {
        $discount = $this->type === CouponType::Percentage
            ? (int) round($subtotal * $this->value / 100)
            : $this->value;

        return min($discount, $subtotal);
    }
}
