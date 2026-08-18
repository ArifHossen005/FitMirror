<?php

namespace App\Services\Billing;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Str;

/**
 * The gateway-facing half of "start a checkout" — shared by
 * PaymentService (plan purchases) and AddonPurchaseService (add-on
 * purchases), since both need the exact same Payment-row-then-SSLCommerz-
 * session-then-handle-failure sequence against an already-priced Invoice.
 * Pricing (what subtotal/discount/vat/total an Invoice gets) stays each
 * caller's own concern — this only ever reads `$invoice->total`.
 *
 * Deliberately NOT one transaction end-to-end: the Payment row is created
 * and committed first, then the SSLCommerz call happens outside any
 * transaction, so a gateway failure updates the already-committed row to
 * Failed instead of rolling the whole attempt back (see PROGRESS.md Phase
 * 3.C's "Fixed" changelog entry for the bug this avoids).
 */
class GatewayCheckoutService extends BaseService
{
    public function __construct(private readonly SslCommerzService $gateway) {}

    /**
     * @return array{payment: Payment, gateway_url: string}
     */
    public function beginCheckout(Tenant $tenant, User $owner, Invoice $invoice, string $productName): array
    {
        $payment = $this->transaction(fn () => Payment::query()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGateway::SslCommerz,
            'gateway_txn_id' => $this->generateTranId($invoice),
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Pending,
        ]));

        $sessionPayload = $this->buildSessionPayload($payment, $invoice, $tenant, $owner, $productName);

        try {
            $response = $this->gateway->initiateSession($sessionPayload);
        } catch (PaymentGatewayException $e) {
            $payment->appendRawPayload('initiate_request', $sessionPayload);
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();

            throw $e;
        }

        $payment->appendRawPayload('initiate_request', $sessionPayload);
        $payment->appendRawPayload('initiate_response', $response);

        if (($response['status'] ?? null) !== 'SUCCESS' || empty($response['GatewayPageURL'])) {
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();

            throw new PaymentGatewayException(
                $response['failedreason'] ?? 'SSLCommerz session initiation failed for an unspecified reason.',
            );
        }

        $payment->save();

        return ['payment' => $payment, 'gateway_url' => $response['GatewayPageURL']];
    }

    private function generateTranId(Invoice $invoice): string
    {
        return 'FM' . now()->format('YmdHis') . Str::upper(Str::random(6)) . $invoice->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSessionPayload(
        Payment $payment,
        Invoice $invoice,
        Tenant $tenant,
        User $owner,
        string $productName,
    ): array {
        return [
            'total_amount' => number_format($payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'tran_id' => $payment->gateway_txn_id,
            'success_url' => config('sslcommerz.success_url'),
            'fail_url' => config('sslcommerz.fail_url'),
            'cancel_url' => config('sslcommerz.cancel_url'),
            'ipn_url' => config('sslcommerz.ipn_url'),
            'shipping_method' => 'NO',
            'product_name' => "{$productName} — invoice {$invoice->number}",
            'product_category' => 'SaaS Subscription',
            'product_profile' => 'non-physical-goods',
            'cus_name' => $owner->name,
            'cus_email' => $owner->email,
            'cus_add1' => $tenant->name,
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $owner->phone ?? 'N/A',
            'value_a' => (string) $tenant->id,
            'value_b' => (string) $invoice->id,
        ];
    }
}
