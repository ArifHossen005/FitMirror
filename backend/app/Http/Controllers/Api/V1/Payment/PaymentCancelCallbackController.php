<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Payment;
use App\Services\Billing\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST {SSLC_CANCEL_URL} — see PaymentSuccessCallbackController's docblock
 * for the shared reasoning on auth/tenant middleware and the frontend
 * redirect target.
 */
class PaymentCancelCallbackController extends BaseApiController
{
    public function __invoke(Request $request, PaymentService $payments): RedirectResponse
    {
        $tranId = (string) $request->input('tran_id');
        $payment = Payment::withoutTenantScope()->where('gateway_txn_id', $tranId)->first();

        if ($payment) {
            $payments->markCancelled($payment, $request->all());
        }

        $url = rtrim(config('app.frontend_url'), '/') . '/billing/payment/cancelled';

        return redirect()->away($payment ? "{$url}?invoice=" . urlencode($payment->invoiceUnscoped()->number) : $url);
    }
}
