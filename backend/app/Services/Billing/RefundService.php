<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * Refund initiation — a Refund ledger row is always created and kept, even
 * when the gateway call itself throws, so a rejected-tenant refund attempt
 * is never silently lost (see Refund's own docblock). The "auto-refund
 * trigger when Mission Control rejects a tenant" checklist item that would
 * *call* this automatically is still SKIPPED — Mission Control has no
 * tenant-reject endpoint yet (that's Phase 13); this is the primitive that
 * trigger will call once it exists, the same "build the primitive ahead of
 * its caller" pattern as SubscriptionService's cancel()/startTrial().
 */
class RefundService extends BaseService
{
    public function __construct(private readonly SslCommerzService $gateway) {}

    public function refund(Payment $payment, int $amount, ?string $reason = null): Refund
    {
        if ($payment->status !== PaymentStatus::Success) {
            throw ValidationException::withMessages([
                'payment' => ['Only a successfully captured payment can be refunded.'],
            ]);
        }

        if ($amount <= 0 || $amount > $payment->amount) {
            throw ValidationException::withMessages([
                'amount' => ['Refund amount must be between 1 and the original payment amount.'],
            ]);
        }

        $bankTranId = $payment->raw_payload['validation']['bank_tran_id'] ?? null;

        if ($payment->gateway->value === 'sslcommerz' && empty($bankTranId)) {
            throw new PaymentGatewayException(
                'Cannot refund: no bank_tran_id recorded for this payment (missing from its stored order-validation payload).',
            );
        }

        return $this->transaction(function () use ($payment, $amount, $reason, $bankTranId) {
            $refund = Refund::query()->create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reason' => $reason,
                'status' => RefundStatus::Pending,
            ]);

            try {
                $response = $payment->gateway->value === 'manual'
                    ? ['status' => 'success', 'note' => 'Manual payment — refunded outside SSLCommerz, no gateway call made.']
                    : $this->gateway->initiateRefund($bankTranId, $amount, $reason ?? 'FitMirror refund');
            } catch (PaymentGatewayException $e) {
                $refund->forceFill([
                    'status' => RefundStatus::Failed,
                    'raw_payload' => ['error' => $e->getMessage()],
                ])->save();

                throw $e;
            }

            $succeeded = $payment->gateway->value === 'manual' || ($response['status'] ?? null) === 'success';

            $refund->forceFill([
                'status' => $succeeded ? RefundStatus::Completed : RefundStatus::Failed,
                'gateway_refund_ref' => $response['refund_ref_id'] ?? null,
                'raw_payload' => $response,
            ])->save();

            if ($succeeded) {
                $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
                $payment->invoiceUnscoped()->forceFill(['status' => InvoiceStatus::Refunded])->save();
            }

            return $refund;
        });
    }
}
