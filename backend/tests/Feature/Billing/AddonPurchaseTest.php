<?php

namespace Tests\Feature\Billing;

use App\Enums\AddonStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TenantStatus;
use App\Models\Addon;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddonPurchaseTest extends TestCase
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
    private function activeTenantWithOwner(): array
    {
        $tenant = Tenant::factory()->status(TenantStatus::Active)->create(['plan_id' => null]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $owner->assignRole('owner');
        $tenant->forceFill(['owner_id' => $owner->id])->save();

        return [$tenant, $owner];
    }

    public function test_the_addon_catalog_lists_only_active_addons(): void
    {
        [, $owner] = $this->activeTenantWithOwner();
        Addon::factory()->create(['code' => 'active-1']);
        Addon::factory()->status(AddonStatus::Disabled)->create(['code' => 'disabled-1']);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/billing/addons');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data');
        $codes = collect($data)->pluck('code');
        $this->assertContains('active-1', $codes);
        $this->assertNotContains('disabled-1', $codes);
    }

    public function test_purchasing_an_addon_initiates_an_sslcommerz_session_with_vat_applied(): void
    {
        [$tenant, $owner] = $this->activeTenantWithOwner();
        $addon = Addon::factory()->create(['code' => 'sms', 'price' => 500, 'unit_amount' => 500]);

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gwprocess/pay/addon1',
            ], 200),
        ]);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/billing/addons/{$addon->id}/purchase", ['quantity' => 2]);

        $response->assertOk();
        $expectedTotal = (int) round(1000 * 1.15);
        $response->assertJsonPath('data.amount', $expectedTotal);

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'addon_id' => $addon->id,
            'subscription_id' => null,
            'total' => $expectedTotal,
        ]);
    }

    public function test_a_verified_addon_payment_credits_the_tenants_balance(): void
    {
        [$tenant, $owner] = $this->activeTenantWithOwner();
        $addon = Addon::factory()->create(['code' => 'storage', 'price' => 300, 'unit_amount' => 10]);

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gwprocess/pay/addon2',
            ], 200),
        ]);

        $token = $owner->createToken('t')->plainTextToken;
        $init = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/billing/addons/{$addon->id}/purchase", ['quantity' => 3]);
        $init->assertOk();

        $payment = Payment::withoutTenantScope()->where('tenant_id', $tenant->id)->firstOrFail();
        $invoice = Invoice::withoutTenantScope()->findOrFail($payment->invoice_id);

        Http::fake([
            '*/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'amount' => number_format($payment->amount, 2, '.', ''),
            ], 200),
        ]);

        $callback = $this->postJson('/api/v1/payment/callback/success', [
            'tran_id' => $payment->gateway_txn_id,
            'val_id' => 'val_addon',
        ]);
        $callback->assertRedirect();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $tenantAddon = TenantAddon::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('addon_id', $addon->id)
            ->firstOrFail();

        $this->assertSame(30, $tenantAddon->remaining_balance); // unit_amount(10) * qty(3)
    }

    public function test_purchasing_an_unavailable_addon_is_rejected(): void
    {
        [, $owner] = $this->activeTenantWithOwner();
        $addon = Addon::factory()->status(AddonStatus::Disabled)->create();

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/billing/addons/{$addon->id}/purchase", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('addon');
    }
}
