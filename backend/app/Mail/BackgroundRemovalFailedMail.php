<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent by App\Jobs\RemoveBackgroundJob::failed() once every retry is
 * exhausted. Deliberately not a Laravel Notification — Phase 11
 * (Notification System) doesn't exist yet, so this follows InvoiceMail's
 * exact shape: a plain Mailable, dispatched from a job already holding a
 * safely re-fetched (never tenant-scoped-and-ambient) model, never itself
 * `ShouldQueue` for the same SerializesModels double-re-fetch reason
 * documented on InvoiceMail.
 */
class BackgroundRemovalFailedMail extends Mailable
{
    public function __construct(private readonly Product $product, private readonly ProductImage $image) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "FitMirror — background removal failed for \"{$this->product->name}\"");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.background-removal-failed', with: [
            'product' => $this->product,
            'image' => $this->image,
        ]);
    }
}
