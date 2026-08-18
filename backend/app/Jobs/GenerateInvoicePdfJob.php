<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Billing\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Dispatched by PaymentService::finalizeInvoice() the moment any invoice
 * (plan or add-on) is marked Paid. Takes an invoice *id*, not an Invoice
 * model — Laravel's SerializesModels (pulled in by Queueable) re-fetches a
 * typed model constructor property through its own scoped query when a
 * job is unserialized on the worker, and a plain `queue:work` worker
 * (Decision D-01) has no ambient TenantContext for that re-fetch to run
 * against; TenantScope would fail closed and the job would silently do
 * nothing. Explicitly re-fetching via withoutTenantScope() in handle()
 * avoids that — the same class of bug as Decision D-13, avoided here by
 * construction rather than caught by a failing test.
 */
class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(private readonly int $invoiceId)
    {
        $this->onQueue('notifications');
    }

    public function handle(InvoicePdfService $pdfService): void
    {
        $invoice = Invoice::withoutTenantScope()->findOrFail($this->invoiceId);

        $path = $pdfService->generate($invoice);

        $invoice->forceFill(['pdf_path' => $path])->save();

        SendInvoiceEmailJob::dispatch($invoice->id);
    }
}
