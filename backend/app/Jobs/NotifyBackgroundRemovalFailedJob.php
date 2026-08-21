<?php

namespace App\Jobs;

use App\Mail\BackgroundRemovalFailedMail;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by RemoveBackgroundJob::failed() once every retry is
 * exhausted. Same withoutTenantScope()-through-Tenant, never-through-a-
 * scoped-relation shape as SendInvoiceEmailJob — see that job's own
 * docblock for the exact bug class this avoids.
 */
class NotifyBackgroundRemovalFailedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(private readonly int $productImageId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $image = ProductImage::withoutTenantScope()->find($this->productImageId);

        if ($image === null) {
            return;
        }

        $product = Product::withoutTenantScope()->with('tenant')->find($image->product_id);

        if ($product === null) {
            return;
        }

        $ownerId = $product->tenant->owner_id;
        $recipient = $ownerId ? User::withoutTenantScope()->find($ownerId)?->email : null;

        if (!$recipient) {
            return;
        }

        Mail::to($recipient)->send(new BackgroundRemovalFailedMail($product, $image));
    }
}
