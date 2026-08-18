<?php

namespace Tests\Feature\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SuperAdminRole;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Services\Billing\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sslcommerz.store_id' => 'test_store', 'sslcommerz.store_password' => 'test_pass']);
    }

    private function successfulSslCommerzPayment(): Payment
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $invoice = Invoice::factory()->for($tenant)->status(InvoiceStatus::Paid)->create();

        return Payment::factory()->for($tenant)->create([
            'invoice_id' => $invoice->id,
            'status' => PaymentStatus::Success,
            'raw_payload' => ['validation' => ['bank_tran_id' => 'BANK123']],
        ]);
    }

    public function test_refunding_without_a_configured_endpoint_throws_a_clear_gateway_exception(): void
    {
        $payment = $this->successfulSslCommerzPayment();

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('SSLC_REFUND_INITIATE_ENDPOINT');

        app(RefundService::class)->refund($payment, $payment->amount);
    }

    public function test_a_configured_endpoint_completes_the_refund_and_updates_the_ledger(): void
    {
        config(['sslcommerz.refund_initiate_endpoint' => 'https://sandbox.sslcommerz.com/validator/api/refund-test']);
        $payment = $this->successfulSslCommerzPayment();

        Http::fake([
            'sandbox.sslcommerz.com/validator/api/refund-test*' => Http::response([
                'status' => 'success',
                'refund_ref_id' => 'REF123',
            ], 200),
        ]);

        $refund = app(RefundService::class)->refund($payment, $payment->amount, 'Tenant rejected');

        $this->assertSame(RefundStatus::Completed, $refund->status);
        $this->assertSame('REF123', $refund->gateway_refund_ref);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Refunded, $payment->invoiceUnscoped()->status);
    }

    public function test_a_manual_payment_refunds_without_calling_the_gateway_at_all(): void
    {
        $payment = $this->successfulSslCommerzPayment();
        $payment->forceFill(['gateway' => PaymentGateway::Manual])->save();

        Http::fake();

        $refund = app(RefundService::class)->refund($payment, $payment->amount);

        $this->assertSame(RefundStatus::Completed, $refund->status);
        Http::assertNothingSent();
    }

    public function test_refunding_more_than_the_original_amount_is_rejected(): void
    {
        $payment = $this->successfulSslCommerzPayment();

        $this->expectException(ValidationException::class);

        app(RefundService::class)->refund($payment, $payment->amount + 1);
    }

    public function test_refunding_a_payment_that_was_never_successful_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $invoice = Invoice::factory()->for($tenant)->create();
        $payment = Payment::factory()->for($tenant)->create([
            'invoice_id' => $invoice->id,
            'status' => PaymentStatus::Failed,
        ]);

        $this->expectException(ValidationException::class);

        app(RefundService::class)->refund($payment, $payment->amount);
    }

    public function test_finance_can_refund_via_the_mission_control_endpoint(): void
    {
        config(['sslcommerz.refund_initiate_endpoint' => 'https://sandbox.sslcommerz.com/validator/api/refund-test']);
        $payment = $this->successfulSslCommerzPayment();

        Http::fake([
            'sandbox.sslcommerz.com/validator/api/refund-test*' => Http::response(['status' => 'success', 'refund_ref_id' => 'REF999'], 200),
        ]);

        $finance = SuperAdmin::factory()->role(SuperAdminRole::Finance)->create();
        $token = $finance->createToken('mc')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/mission/payments/{$payment->id}/refund", ['reason' => 'Tenant rejected']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'completed');
    }

    public function test_support_role_cannot_issue_a_refund(): void
    {
        $payment = $this->successfulSslCommerzPayment();
        $support = SuperAdmin::factory()->role(SuperAdminRole::Support)->create();
        $token = $support->createToken('mc')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/mission/payments/{$payment->id}/refund", []);

        $response->assertForbidden();
    }
}
