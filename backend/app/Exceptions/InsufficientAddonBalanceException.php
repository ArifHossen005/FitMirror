<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Services\Billing\AddonConsumptionService::consume() when a
 * tenant's usable TenantAddon balance for a given add-on type is less than
 * the amount requested — caught centrally by App\Support\
 * ApiExceptionRenderer and rendered as 402 (Payment Required), the
 * standard HTTP status for "you need to buy more of this".
 */
class InsufficientAddonBalanceException extends RuntimeException
{
    public function __construct(
        public readonly string $addonCode,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Insufficient '{$addonCode}' balance: requested {$requested}, {$available} available.",
        );
    }
}
