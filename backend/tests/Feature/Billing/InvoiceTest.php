<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingCycle;
use App\Enums\TenantStatus;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

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

    /** Records a real, fully-finalized (Paid + PDF generated) invoice via the offline path — simplest way to get a real Paid invoice without mocking SSLCommerz. */
    private function paidInvoiceFor(Tenant $tenant): Invoice
    {
        Mail::fake();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $superAdmin = SuperAdmin::factory()->create();

        $payment = app(PaymentService::class)->recordOffline($tenant, $plan, BillingCycle::Monthly, null, $superAdmin, 'test');

        return $payment->invoiceUnscoped();
    }

    public function test_the_owner_can_list_their_invoices(): void
    {
        [$tenant, $owner] = $this->activeTenantWithOwner();
        $this->paidInvoiceFor($tenant);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/billing/invoices');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.type', 'plan');
        $response->assertJsonPath('data.0.downloadable', true);
    }

    public function test_a_manager_without_billing_permission_cannot_list_invoices(): void
    {
        [$tenant] = $this->activeTenantWithOwner();
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');

        $token = $manager->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/billing/invoices');

        $response->assertForbidden();
    }

    public function test_downloading_a_generated_invoice_pdf_succeeds(): void
    {
        [$tenant, $owner] = $this->activeTenantWithOwner();
        $invoice = $this->paidInvoiceFor($tenant);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/billing/invoices/{$invoice->id}/download");

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_downloading_an_invoice_with_no_pdf_yet_returns_a_clear_404(): void
    {
        [$tenant, $owner] = $this->activeTenantWithOwner();
        $invoice = Invoice::factory()->for($tenant)->create(['pdf_path' => null]);

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/billing/invoices/{$invoice->id}/download");

        $response->assertNotFound();
        $response->assertJsonPath('error_code', 'invoice_pdf_not_ready');
    }

    public function test_a_tenant_cannot_download_another_tenants_invoice(): void
    {
        [, $ownerA] = $this->activeTenantWithOwner();
        [$tenantB] = $this->activeTenantWithOwner();
        $invoiceB = $this->paidInvoiceFor($tenantB);

        $token = $ownerA->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/billing/invoices/{$invoiceB->id}/download");

        $response->assertNotFound();
    }
}
