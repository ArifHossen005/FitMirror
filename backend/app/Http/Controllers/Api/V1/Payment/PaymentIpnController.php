<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Payment;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST {SSLC_IPN_URL} — SSLCommerz's server-to-server Instant Payment
 * Notification, independent of whether the customer's browser ever makes
 * it back to the success callback (closed tab, network drop mid-redirect).
 * Always responds 200 so SSLCommerz doesn't endlessly retry delivery —
 * even a payment that fails order-validation here has been durably
 * recorded as Failed, which is a terminal, acknowledged outcome from
 * SSLCommerz's point of view. Safe to receive more than once for the same
 * tran_id: PaymentService::verifyAndMarkSuccess() is idempotent on an
 * already-Success payment, which is exactly what makes IPN replay safe.
 */
class PaymentIpnController extends BaseApiController
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $tranId = (string) $request->input('tran_id');
        $valId = $request->input('val_id');
        $status = $request->input('status');

        $payment = Payment::withoutTenantScope()->where('gateway_txn_id', $tranId)->first();

        if (!$payment) {
            return $this->success(null, 'No matching payment for this tran_id — acknowledged.');
        }

        if ($status === 'VALID' && $valId) {
            try {
                $payments->verifyAndMarkSuccess($payment, (string) $valId, $request->all());
            } catch (PaymentGatewayException) {
                // Already recorded as Failed by verifyAndMarkSuccess() itself.
            }

            return $this->success(null, 'IPN processed.');
        }

        $payments->markFailed($payment, $request->all());

        return $this->success(null, 'IPN processed.');
    }
}
