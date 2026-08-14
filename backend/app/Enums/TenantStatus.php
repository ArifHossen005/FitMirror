<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 * Lifecycle: pending → trial|active (on approval) → suspended|expired are
 * reversible; rejected is terminal (set by Mission Control instead of the
 * tenant approval flow completing). See PROGRESS.md Phase 3.B for the full
 * subscription-driven transitions once SubscriptionStatus exists.
 */
enum TenantStatus: string
{
    case Pending = 'pending';
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Rejected = 'rejected';

    /** Whether the tenant should be allowed to use the product at all. */
    public function isUsable(): bool
    {
        return match ($this) {
            self::Trial, self::Active => true,
            self::Pending, self::Suspended, self::Expired, self::Rejected => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Expired => 'Expired',
            self::Rejected => 'Rejected',
        };
    }
}
