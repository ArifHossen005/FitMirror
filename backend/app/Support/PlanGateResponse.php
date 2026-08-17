<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one place a plan-gated 403 is built — feature unavailable (
 * EnforcePlanFeature) and limit exceeded (PlanLimitExceededException /
 * StaffInvitationService, etc.) both go through this so the client always
 * gets the same shape to build an upgrade prompt from, per PROGRESS.md
 * Phase 3.A's "return limit-exceeded errors with an upgrade CTA payload".
 */
class PlanGateResponse
{
    public static function featureUnavailable(string $featureKey): JsonResponse
    {
        return ApiResponse::error(
            'Your current plan does not include this feature.',
            Response::HTTP_FORBIDDEN,
            errorCode: 'plan_feature_unavailable',
            errors: [
                'feature' => $featureKey,
                'upgrade_url' => self::upgradeUrl(),
            ],
        );
    }

    public static function limitExceeded(string $limitKey, int $current, int $limit): JsonResponse
    {
        return ApiResponse::error(
            'You have reached your plan\'s limit for this.',
            Response::HTTP_FORBIDDEN,
            errorCode: 'plan_limit_exceeded',
            errors: [
                'limit' => $limitKey,
                'current' => $current,
                'max' => $limit,
                'upgrade_url' => self::upgradeUrl(),
            ],
        );
    }

    private static function upgradeUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/settings/billing';
    }
}
