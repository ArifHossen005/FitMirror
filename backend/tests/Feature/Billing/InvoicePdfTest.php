<?php

namespace Tests\Feature\Billing;

use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_renders_and_stores_a_real_pdf_on_the_tenant_disk(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $invoice = Invoice::factory()->for($tenant)->create(['number' => 'INV-TEST-000001']);
        $invoice->items()->create(['description' => 'Pro plan', 'qty' => 1, 'unit_price' => 499, 'total' => 499]);

        $path = app(InvoicePdfService::class)->generate($invoice->fresh());

        $this->assertSame("tenants/{$tenant->id}/invoices/INV-TEST-000001.pdf", $path);
        Storage::disk('tenant')->assertExists($path);
        $this->assertStringStartsWith('%PDF', Storage::disk('tenant')->get($path));
    }

    public function test_finalizing_an_invoice_dispatches_pdf_generation(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $invoice = Invoice::factory()->for($tenant)->create();

        GenerateInvoicePdfJob::dispatch($invoice->id);

        Queue::assertPushed(GenerateInvoicePdfJob::class, fn (GenerateInvoicePdfJob $job) => true);
    }

    public function test_the_pdf_job_chains_into_sending_the_invoice_email(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'owner@example.com']);
        $tenant->forceFill(['owner_id' => $owner->id])->save();
        $invoice = Invoice::factory()->for($tenant)->create();
        $invoice->items()->create(['description' => 'Pro plan', 'qty' => 1, 'unit_price' => 499, 'total' => 499]);

        app(GenerateInvoicePdfJob::class, ['invoiceId' => $invoice->id])->handle(app(InvoicePdfService::class));
        $this->assertNotNull($invoice->fresh()->pdf_path);

        app(SendInvoiceEmailJob::class, ['invoiceId' => $invoice->id])->handle();

        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    }

    public function test_the_email_job_does_nothing_if_the_pdf_was_never_generated(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->forceFill(['owner_id' => $owner->id])->save();
        $invoice = Invoice::factory()->for($tenant)->create(['pdf_path' => null]);

        app(SendInvoiceEmailJob::class, ['invoiceId' => $invoice->id])->handle();

        Mail::assertNothingSent();
    }
}
