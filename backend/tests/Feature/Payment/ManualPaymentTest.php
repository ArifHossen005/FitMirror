<?php

namespace Tests\Feature\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\SubscriptionStatus;
use App\Enums\SuperAdminRole;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_record_an_offline_payment_which_activates_the_subscription(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $finance = SuperAdmin::factory()->role(SuperAdminRole::Finance)->create();
        $token = $finance->createToken('mc')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/mission/tenants/{$tenant->id}/payments", [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'note' => 'Bank transfer confirmed by ops',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount', $plan->price_monthly);
        $response->assertJsonPath('data.status', 'success');

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'gateway' => PaymentGateway::Manual->value,
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => SubscriptionStatus::PendingApproval->value,
        ]);
    }

    public function test_support_role_cannot_record_a_manual_payment(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $support = SuperAdmin::factory()->role(SuperAdminRole::Support)->create();
        $token = $support->createToken('mc')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/mission/tenants/{$tenant->id}/payments", [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertForbidden();
    }

    public function test_a_custom_amount_overrides_the_plans_list_price(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $superAdmin = SuperAdmin::factory()->create();
        $token = $superAdmin->createToken('mc')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/mission/tenants/{$tenant->id}/payments", [
                'plan_id' => $plan->id,
                'billing_cycle' => 'yearly',
                'amount' => 10_000,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount', 10000);
    }
}
