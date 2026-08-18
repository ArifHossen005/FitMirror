<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingCycle;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Billing\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_history_merges_payments_and_refunds_newest_first(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->status(TenantStatus::Active)->create(['plan_id' => null]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $owner->assignRole('owner');
        $tenant->forceFill(['owner_id' => $owner->id])->save();

        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $superAdmin = SuperAdmin::factory()->create();

        // recordOffline() sets gateway=Manual, so RefundService::refund()
        // below completes without any real SSLCommerz call — see its own
        // 'manual' branch.
        $payment = app(PaymentService::class)->recordOffline($tenant, $plan, BillingCycle::Monthly, null, $superAdmin, null);

        app(RefundService::class)->refund($payment, $payment->amount, 'test refund');

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/billing/history');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $response->json('data');
        $this->assertCount(2, $rows);
        $types = collect($rows)->pluck('type');
        $this->assertContains('payment', $types);
        $this->assertContains('refund', $types);
    }
}
