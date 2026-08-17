<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by a service (e.g. StaffInvitationService) when a tenant's plan
 * limit would be exceeded by the action being attempted — caught centrally
 * by App\Support\ApiExceptionRenderer and rendered via
 * App\Support\PlanGateResponse::limitExceeded(), so every limit check
 * across every module produces the identical response shape without each
 * service needing to build a JsonResponse itself.
 */
class PlanLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $limitKey,
        public readonly int $current,
        public readonly int $max,
    ) {
        parent::__construct("Plan limit '{$limitKey}' exceeded ({$current}/{$max}).");
    }
}
