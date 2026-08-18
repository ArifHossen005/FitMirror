<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Payment;
use App\Services\Billing\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST {SSLC_SUCCESS_URL} — SSLCommerz redirects the customer's browser
 * here via an auto-submitting form after a completed session. Not behind
 * auth:sanctum (the browser arrives with no FitMirror session, only
 * SSLCommerz's posted fields) and not behind ResolveTenant's usual
 * middleware chain — the Payment row itself, looked up by tran_id, is
 * this request's only source of truth. Always redirects onward to
 * config('app.frontend_url') so the customer lands somewhere real even
 * though Phase 3.E's actual result pages don't exist yet (same
 * ahead-of-its-page pattern as the password-reset email link).
 */
class PaymentSuccessCallbackController extends BaseApiController
{
    public function __invoke(Request $request, PaymentService $payments): RedirectResponse
    {
        $tranId = (string) $request->input('tran_id');
        $valId = $request->input('val_id');

        $payment = Payment::withoutTenantScope()->where('gateway_txn_id', $tranId)->first();

        if (!$payment || !$valId) {
            return redirect()->away($this->resultUrl('failed'));
        }

        try {
            $payments->verifyAndMarkSuccess($payment, (string) $valId, $request->all());
        } catch (PaymentGatewayException) {
            return redirect()->away($this->resultUrl('failed', $payment->invoiceUnscoped()->number));
        }

        return redirect()->away($this->resultUrl('success', $payment->invoiceUnscoped()->number));
    }

    private function resultUrl(string $status, ?string $invoiceNumber = null): string
    {
        $url = rtrim(config('app.frontend_url'), '/') . "/billing/payment/{$status}";

        return $invoiceNumber ? "{$url}?invoice=" . urlencode($invoiceNumber) : $url;
    }
}
