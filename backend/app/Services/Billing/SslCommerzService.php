<?php

namespace App\Services\Billing;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over SSLCommerz's REST API using Laravel's Http facade
 * (not a dedicated SDK — SSLCommerz's own PHP SDK targets plain cURL, and
 * a hand-rolled Http::fake()-mockable client is a better fit for this
 * codebase's test conventions than wrapping a third-party SDK would be).
 * Every method throws PaymentGatewayException rather than returning a
 * malformed/empty array on failure, so callers never have to null-check a
 * gateway response by hand.
 */
class SslCommerzService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function initiateSession(array $payload): array
    {
        $this->assertCredentialsConfigured();

        $response = Http::asForm()->post($this->endpoint('session'), array_merge($this->credentials(), $payload));

        $data = $response->json();

        if (!is_array($data)) {
            throw new PaymentGatewayException('SSLCommerz session initiate returned a non-JSON response.');
        }

        return $data;
    }

    /**
     * The `validationserverAPI` order-validation call — the server-to-
     * server step that stands in for a client-side signature/hash check:
     * SSLCommerz's classic REST integration proves a transaction is
     * genuine by asking SSLCommerz directly for the transaction behind a
     * given val_id, rather than by verifying a signed payload the gateway
     * sent us. A response is only trusted once its `status` is `VALID` or
     * `VALIDATED` *and* its `amount` matches what we expect — see
     * PaymentService::verifyAndMarkSuccess(), which is the caller that
     * actually enforces both checks.
     *
     * @return array<string, mixed>
     */
    public function validateTransaction(string $valId): array
    {
        $this->assertCredentialsConfigured();

        $response = Http::get($this->endpoint('validation'), array_merge($this->credentials(), [
            'val_id' => $valId,
            'format' => 'json',
        ]));

        $data = $response->json();

        if (!is_array($data)) {
            throw new PaymentGatewayException('SSLCommerz order validation returned a non-JSON response.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentGatewayException if SSLC_REFUND_INITIATE_ENDPOINT
     *                                 isn't configured — see config/sslcommerz.php's own docblock for why
     *                                 this isn't defaulted to a guessed URL.
     */
    public function initiateRefund(string $bankTranId, int $refundAmount, string $refundRemarks): array
    {
        $this->assertCredentialsConfigured();

        $endpoint = config('sslcommerz.refund_initiate_endpoint');

        if (empty($endpoint)) {
            throw new PaymentGatewayException(
                'SSLC_REFUND_INITIATE_ENDPOINT is not configured. Confirm the correct path against the '
                . 'SSLCommerz merchant panel\'s API reference before issuing a refund — see config/sslcommerz.php.',
            );
        }

        $response = Http::asForm()->post($endpoint, array_merge($this->credentials(), [
            'bank_tran_id' => $bankTranId,
            'refund_amount' => number_format($refundAmount, 2, '.', ''),
            'refund_remarks' => $refundRemarks,
            'format' => 'json',
        ]));

        $data = $response->json();

        if (!is_array($data)) {
            throw new PaymentGatewayException('SSLCommerz refund initiate returned a non-JSON response.');
        }

        return $data;
    }

    /**
     * @return array<string, string|null>
     */
    private function credentials(): array
    {
        return [
            'store_id' => config('sslcommerz.store_id'),
            'store_passwd' => config('sslcommerz.store_password'),
        ];
    }

    private function assertCredentialsConfigured(): void
    {
        if (empty(config('sslcommerz.store_id')) || empty(config('sslcommerz.store_password'))) {
            throw new PaymentGatewayException(
                'SSLCommerz credentials are not configured (SSLC_STORE_ID / SSLC_STORE_PASSWORD) — '
                . 'see PROGRESS.md Phase 3.C\'s blocker note.',
            );
        }
    }

    private function endpoint(string $type): string
    {
        $mode = config('sslcommerz.sandbox') ? 'sandbox' : 'live';

        return config("sslcommerz.{$type}_endpoint.{$mode}");
    }
}
