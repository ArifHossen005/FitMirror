<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by GenerateInvoicePdfJob once `pdf_path` is set. Takes an
 * invoice *id*, not a model — see GenerateInvoicePdfJob's own docblock for
 * why (Decision D-13-class SerializesModels re-fetch pitfall).
 */
class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(private readonly int $invoiceId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $invoice = Invoice::withoutTenantScope()->with('tenant')->findOrFail($this->invoiceId);

        if (!$invoice->pdf_path) {
            return;
        }

        // Deliberately User::withoutTenantScope() rather than eager-loading
        // $invoice->tenant->owner — Tenant itself carries no TenantScope
        // (it's the one exception, see its own class docblock), but User
        // does, and this job runs on a plain `queue:work` worker with no
        // ambient TenantContext (Decision D-01: one request per process,
        // no cross-request reuse, and a queued job is its own "request").
        // Eager-loading through the relation would silently resolve to
        // null and this method would return early forever — the exact
        // class of bug Decision D-13 describes, caught here the same way:
        // a real feature test (InvoicePdfTest), not inspection.
        $ownerId = $invoice->tenant->owner_id;
        $recipient = $ownerId ? User::withoutTenantScope()->find($ownerId)?->email : null;

        if (!$recipient) {
            return;
        }

        Mail::to($recipient)->send(new InvoiceMail($invoice, $invoice->pdf_path));
    }
}
