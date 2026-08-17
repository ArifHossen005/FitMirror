<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 *
 * A separate state machine from TenantStatus (App\Enums\TenantStatus) —
 * TenantStatus governs whether the tenant can use the product *at all*;
 * this governs the *billing relationship* driving those transitions (see
 * TenantStatus's own docblock, written in Phase 2.A ahead of this enum
 * existing). A subscription's status changes are expected to push the
 * tenant's own status accordingly once Phase 3.C's payment webhooks exist
 * to drive them — that wiring isn't built yet (see PROGRESS.md Phase 3.B),
 * only the state machine itself.
 */
enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case PendingApproval = 'pending_approval';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::PendingApproval => 'Pending Approval',
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::PastDue => 'Past Due',
            self::Grace => 'Grace Period',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function isUsable(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue, self::Grace => true,
            default => false,
        };
    }

    /**
     * The complete, deliberate transition graph — anything not listed
     * here is an invalid transition and Subscription::transitionTo()
     * throws rather than allowing it. See this enum's own docblock for
     * the product reasoning behind each edge.
     *
     * @return list<self>
     */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::PendingPayment => [self::PendingApproval, self::Cancelled],
            self::PendingApproval => [self::Active, self::Cancelled],
            self::Trialing => [self::Active, self::Expired, self::Cancelled],
            self::Active => [self::PastDue, self::Cancelled],
            self::PastDue => [self::Active, self::Grace],
            self::Grace => [self::Active, self::Suspended],
            self::Suspended => [self::Active, self::Cancelled],
            self::Cancelled => [self::Active],
            self::Expired => [self::Active, self::Trialing],
        };
    }
}
