<?php

namespace Tests\Feature\Billing;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Enums\InvoiceStatus;
use App\Enums\TenantStatus;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponTest extends TestCase
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

    public function test_a_valid_percentage_coupon_previews_the_correct_discount(): void
    {
        [$tenant, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        Coupon::factory()->type(CouponType::Percentage, 20)->create(['code' => 'SAVE20']);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/billing/coupon/preview', [
            'code' => 'SAVE20',
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount', (int) round($plan->price_monthly * 0.2));
    }

    public function test_a_fixed_coupon_is_capped_at_the_subtotal(): void
    {
        [, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        Coupon::factory()->type(CouponType::Fixed, 999999)->create(['code' => 'HUGE']);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/billing/coupon/preview', [
            'code' => 'HUGE',
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount', $plan->price_monthly);
        $response->assertJsonPath('data.total_before_vat', 0);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        [, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/billing/coupon/preview', [
            'code' => 'NOPE',
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('coupon');
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $coupon = Coupon::factory()->expired()->create(['code' => 'OLD']);

        $this->expectException(ValidationException::class);

        app(CouponService::class)->preview('OLD', $tenant, $plan, $plan->price_monthly);
    }

    public function test_a_disabled_coupon_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        Coupon::factory()->status(CouponStatus::Disabled)->create(['code' => 'OFF']);

        $this->expectException(ValidationException::class);

        app(CouponService::class)->preview('OFF', $tenant, $plan, $plan->price_monthly);
    }

    public function test_a_coupon_restricted_to_another_plan_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        Coupon::factory()->appliesTo(['max'])->create(['code' => 'MAXONLY']);

        $this->expectException(ValidationException::class);

        app(CouponService::class)->preview('MAXONLY', $tenant, $pro, $pro->price_monthly);
    }

    public function test_max_redemptions_reached_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $coupon = Coupon::factory()->maxRedemptions(1)->perTenantLimit(null)->create(['code' => 'ONEONLY']);

        $invoice = Invoice::factory()->for(Tenant::factory()->create(['plan_id' => null]))->create();
        app(CouponService::class)->redeem($coupon, $invoice->tenant, $invoice, 50);

        $this->expectException(ValidationException::class);

        app(CouponService::class)->preview('ONEONLY', $tenant, $plan, $plan->price_monthly);
    }

    public function test_per_tenant_limit_reached_is_rejected_for_that_tenant_but_not_others(): void
    {
        $tenantA = Tenant::factory()->create(['plan_id' => null]);
        $tenantB = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $coupon = Coupon::factory()->perTenantLimit(1)->create(['code' => 'ONCE']);

        $invoice = Invoice::factory()->for($tenantA)->create();
        app(CouponService::class)->redeem($coupon, $tenantA, $invoice, 50);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->preview('ONCE', $tenantA, $plan, $plan->price_monthly);
    }

    public function test_a_second_tenant_can_still_use_a_coupon_after_the_first_tenants_per_tenant_limit_is_reached(): void
    {
        $tenantA = Tenant::factory()->create(['plan_id' => null]);
        $tenantB = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $coupon = Coupon::factory()->perTenantLimit(1)->create(['code' => 'ONCE2']);

        $invoiceA = Invoice::factory()->for($tenantA)->create();
        app(CouponService::class)->redeem($coupon, $tenantA, $invoiceA, 50);

        $preview = app(CouponService::class)->preview('ONCE2', $tenantB, $plan, $plan->price_monthly);
        $this->assertNotNull($preview['coupon']);
    }

    public function test_a_coupon_applied_at_checkout_discounts_the_invoice_and_records_a_redemption(): void
    {
        [$tenant, $owner] = $this->pendingTenantWithOwner();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        Coupon::factory()->type(CouponType::Fixed, 100)->create(['code' => 'FLAT100']);

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gwprocess/pay/xyz',
            ], 200),
        ]);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/payment/initiate', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'coupon_code' => 'FLAT100',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount', 100);

        $invoice = Invoice::query()->where('tenant_id', $tenant->id)->where('status', InvoiceStatus::Pending)->firstOrFail();
        $this->assertSame($plan->price_monthly - 100, $invoice->subtotal - $invoice->discount);
        $this->assertDatabaseHas('coupon_redemptions', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'amount_discounted' => 100,
        ]);
    }
}
