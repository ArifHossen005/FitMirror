<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deliberately not `ShouldQueue` — App\Jobs\SendInvoiceEmailJob is the
 * queued unit that dispatches this synchronously via ->send(), already
 * having safely re-fetched $invoice via Invoice::withoutTenantScope()
 * outside any ambient TenantContext. Making this Mailable itself queueable
 * would let Laravel's own SerializesModels re-fetch $invoice a *second*
 * time when the mail job runs — on a plain `queue:work` worker (Decision
 * D-01) that re-fetch has no TenantContext either, and Invoice carries
 * TenantScope, so it would silently resolve to null. Same class of bug as
 * Decision D-13; avoided here by construction rather than caught by a
 * failing test.
 */
class InvoiceMail extends Mailable
{
    public function __construct(private readonly Invoice $invoice, private readonly string $pdfPath) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "FitMirror Invoice {$this->invoice->number} — Payment Received");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice-paid', with: ['invoice' => $this->invoice]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('tenant', $this->pdfPath)
                ->as("{$this->invoice->number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
