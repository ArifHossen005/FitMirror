<?php

namespace Tests\Feature\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentInitiateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sslcommerz.store_id' => 'test_store', 'sslcommerz.store_password' => 'test_pass']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function pendingTenantWithOwner(): array
    {
        $tenant = Tenant::factory()->status(TenantStatus::Pending)->create(['plan_id' => null]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $owner->assignRole('owner');
        $tenant->forceFill(['owner_id' => $owner->id])->save();

        return [$tenant, $owner];
    }

    public function test_a_pending_not_yet_active_tenant_can_still_initiate_payment(): void
    {
        [$tenant, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gwprocess/pay/abc123',
                'sessionkey' => 'abc123',
            ], 200),
        ]);

        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/payment/initiate', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $expectedTotal = $plan->price_monthly + TaxCalculator::vatFor($plan->price_monthly);

        $response->assertOk();
        $response->assertJsonPath('data.gateway_url', 'https://sandbox.sslcommerz.com/gwprocess/pay/abc123');
        $response->assertJsonPath('data.amount', $expectedTotal);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::PendingPayment->value,
        ]);
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatus::Pending->value,
            'total' => $expectedTotal,
        ]);

        Http::assertSent(function ($request) use ($expectedTotal) {
            return str_contains((string) $request->url(), 'gwprocess')
                && $request['store_id'] === 'test_store'
                && $request['total_amount'] === number_format($expectedTotal, 2, '.', '');
        });
    }

    public function test_a_non_owner_cannot_initiate_payment(): void
    {
        [$tenant] = $this->pendingTenantWithOwner();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $token = $staff->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/payment/initiate', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertForbidden();
    }

    public function test_a_tenant_that_already_has_an_active_subscription_cannot_initiate_another(): void
    {
        [$tenant, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::Active,
            'auto_renew' => true,
        ]);

        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/payment/initiate', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('tenant');
    }

    public function test_a_failed_session_initiate_response_is_surfaced_as_a_gateway_error(): void
    {
        [, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
                'status' => 'FAILED',
                'failedreason' => 'Invalid store credentials',
            ], 200),
        ]);

        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/payment/initiate', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('error_code', 'payment_gateway_error');

        $this->assertDatabaseHas('payments', ['status' => 'failed']);
    }

    public function test_missing_credentials_fail_fast_with_a_clear_error(): void
    {
        config(['sslcommerz.store_id' => null, 'sslcommerz.store_password' => null]);
        [, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/payment/initiate', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('error_code', 'payment_gateway_error');
    }
}
