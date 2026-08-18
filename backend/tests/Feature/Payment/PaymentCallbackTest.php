<?php

namespace Tests\Feature\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
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

    public function test_success_callback_verifies_and_activates_the_subscription_toward_pending_approval(): void
    {
        [$subscription, $invoice, $payment] = $this->pendingPaymentAgainstAnInvoice();

        Http::fake([
            '*/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'amount' => number_format($payment->amount, 2, '.', ''),
                'bank_tran_id' => 'BANK123',
                'card_type' => 'bKash',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/payment/callback/success', [
            'tran_id' => $payment->gateway_txn_id,
            'val_id' => 'val_abc',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/billing/payment/success', $response->headers->get('Location'));

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame('val_abc', $payment->fresh()->val_id);
        $this->assertSame('bKash', $payment->fresh()->method);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::PendingApproval, $subscription->fresh()->status);
    }

    public function test_success_callback_with_a_mismatched_amount_marks_the_payment_failed(): void
    {
        [, $invoice, $payment] = $this->pendingPaymentAgainstAnInvoice(499);

        Http::fake([
            '*/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'amount' => '1.00',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/payment/callback/success', [
            'tran_id' => $payment->gateway_txn_id,
            'val_id' => 'val_abc',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/billing/payment/failed', $response->headers->get('Location'));
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Pending, $invoice->fresh()->status);
    }

    public function test_success_callback_is_idempotent_on_replay(): void
    {
        [, , $payment] = $this->pendingPaymentAgainstAnInvoice();

        Http::fake([
            '*/validationserverAPI.php*' => Http::response(['status' => 'VALID', 'amount' => number_format($payment->amount, 2, '.', '')], 200),
        ]);

        $this->postJson('/api/v1/payment/callback/success', ['tran_id' => $payment->gateway_txn_id, 'val_id' => 'val_abc']);
        $this->postJson('/api/v1/payment/callback/success', ['tran_id' => $payment->gateway_txn_id, 'val_id' => 'val_abc']);

        // Only the first call should have hit validationserverAPI at all.
        Http::assertSentCount(1);
    }

    public function test_fail_callback_marks_the_payment_failed_without_touching_the_subscription(): void
    {
        [$subscription, $invoice, $payment] = $this->pendingPaymentAgainstAnInvoice();

        $response = $this->postJson('/api/v1/payment/callback/fail', ['tran_id' => $payment->gateway_txn_id]);

        $response->assertRedirect();
        $this->assertStringContainsString('/billing/payment/failed', $response->headers->get('Location'));
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Pending, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::PendingPayment, $subscription->fresh()->status);
    }

    public function test_cancel_callback_marks_the_payment_cancelled(): void
    {
        [, , $payment] = $this->pendingPaymentAgainstAnInvoice();

        $response = $this->postJson('/api/v1/payment/callback/cancel', ['tran_id' => $payment->gateway_txn_id]);

        $response->assertRedirect();
        $this->assertStringContainsString('/billing/payment/cancelled', $response->headers->get('Location'));
        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
    }

    public function test_a_callback_for_an_unknown_tran_id_does_not_error(): void
    {
        $response = $this->postJson('/api/v1/payment/callback/success', ['tran_id' => 'does-not-exist', 'val_id' => 'x']);

        $response->assertRedirect();
        $this->assertStringContainsString('/billing/payment/failed', $response->headers->get('Location'));
    }

    public function test_offline_manual_payments_are_reported_via_the_manual_gateway_enum(): void
    {
        [, , $payment] = $this->pendingPaymentAgainstAnInvoice();
        $payment->forceFill(['gateway' => PaymentGateway::Manual])->save();

        $this->assertSame(PaymentGateway::Manual, $payment->fresh()->gateway);
    }
}
