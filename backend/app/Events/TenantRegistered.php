<?php

namespace App\Events;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once, immediately after a new tenant + owner user are created by
 * RegistrationService — before any approval/payment has happened. No
 * listener exists yet; Mission Control's "new tenant" notification
 * (Phase 13) and the welcome/verification email flow are the first real
 * consumers. Firing the event now, ahead of its listeners, means every
 * future listener gets the exact same payload shape from day one instead
 * of the event being retrofitted later.
 */
class TenantRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $owner,
    ) {}
}
