<?php

namespace Tests\Feature\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentIpnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sslcommerz.store_id' => 'test_store', 'sslcommerz.store_password' => 'test_pass']);
    }

    /**
     * @return array{0: Subscription, 1: Invoice, 2: Payment}
     */
    private function pendingPaymentAgainstAnInvoice(int $amount = 499): array
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $subscription = Subscription::factory()->for($tenant)->status(SubscriptionStatus::PendingPayment)->create();
        $invoice = Invoice::factory()->for($tenant)->create([
            'subscription_id' => $subscription->id,
            'subtotal' => $amount,
            'total' => $amount,
        ]);
        $payment = Payment::factory()->for($tenant)->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
        ]);

        return [$subscription, $invoice, $payment];
    }

    public function test_a_valid_ipn_activates_the_payment_the_same_way_the_success_callback_does(): void
    {
        [$subscription, $invoice, $payment] = $this->pendingPaymentAgainstAnInvoice();

        Http::fake([
            '*/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'amount' => number_format($payment->amount, 2, '.', ''),
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/payment/ipn', [
            'tran_id' => $payment->gateway_txn_id,
            'val_id' => 'val_abc',
            'status' => 'VALID',
        ]);

        $response->assertOk();
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::PendingApproval, $subscription->fresh()->status);
    }

    public function test_replaying_the_same_ipn_twice_does_not_reprocess_or_double_call_the_gateway(): void
    {
        [, , $payment] = $this->pendingPaymentAgainstAnInvoice();

        Http::fake([
            '*/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'amount' => number_format($payment->amount, 2, '.', ''),
            ], 200),
        ]);

        $payload = ['tran_id' => $payment->gateway_txn_id, 'val_id' => 'val_abc', 'status' => 'VALID'];

        $first = $this->postJson('/api/v1/payment/ipn', $payload);
        $second = $this->postJson('/api/v1/payment/ipn', $payload);

        $first->assertOk();
        $second->assertOk();
        Http::assertSentCount(1);
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
    }

    public function test_an_ipn_reporting_a_non_valid_status_marks_the_payment_failed(): void
    {
        [, , $payment] = $this->pendingPaymentAgainstAnInvoice();

        $response = $this->postJson('/api/v1/payment/ipn', [
            'tran_id' => $payment->gateway_txn_id,
            'status' => 'FAILED',
        ]);

        $response->assertOk();
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_an_ipn_for_an_unknown_tran_id_is_acknowledged_without_error(): void
    {
        $response = $this->postJson('/api/v1/payment/ipn', ['tran_id' => 'unknown', 'status' => 'VALID', 'val_id' => 'x']);

        $response->assertOk();
    }
}
