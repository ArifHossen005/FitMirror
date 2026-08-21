<?php

namespace App\Mail;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The daily low-stock digest — one email per tenant listing every variant
 * at or below its own threshold, not one email per variant (a tenant
 * restocking Eid season shouldn't get twenty separate emails the same
 * morning). Same "plain Mailable, dispatched from a job holding an
 * already safely re-fetched model" shape as InvoiceMail/
 * BackgroundRemovalFailedMail — Phase 11 doesn't exist yet.
 */
class LowStockAlertMail extends Mailable
{
    /**
     * @param Collection<int, ProductVariant> $variants
     */
    public function __construct(private readonly Collection $variants) {}

    public function envelope(): Envelope
    {
        $count = $this->variants->count();

        return new Envelope(subject: "FitMirror — {$count} product variant(s) are low on stock");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.low-stock-alert', with: ['variants' => $this->variants]);
    }
}
