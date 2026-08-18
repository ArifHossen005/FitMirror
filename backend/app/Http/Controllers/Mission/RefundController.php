<?php

namespace App\Http\Controllers\Mission;

use App\Enums\SuperAdminPermission;
use App\Http\Requests\Mission\RefundPaymentRequest;
use App\Models\Payment;
use App\Services\Billing\RefundService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/mission/payments/{payment}/refund — manual counterpart to
 * the still-SKIPPED auto-refund trigger (see RefundService's own
 * docblock): until Phase 13 builds a tenant-reject action to fire that
 * automatically, this is how Support/Finance issue a refund today.
 */
class RefundController extends BaseMissionController
{
    public function store(RefundPaymentRequest $request, int $payment, RefundService $refunds): JsonResponse
    {
        $superAdmin = $this->superAdmin($request);

        if (!$superAdmin->hasPermission(SuperAdminPermission::Billing)) {
            return $this->error(
                trans('common.unauthorized'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'unauthorized',
            );
        }

        $paymentModel = Payment::withoutTenantScope()->findOrFail($payment);
        $amount = $request->validated('amount') ?? $paymentModel->amount;

        $refund = $refunds->refund($paymentModel, $amount, $request->validated('reason'));

        return $this->created([
            'id' => $refund->id,
            'amount' => $refund->amount,
            'status' => $refund->status->value,
            'gateway_refund_ref' => $refund->gateway_refund_ref,
        ], 'Refund processed.');
    }
}
