<?php

namespace App\Jobs;

use App\Mail\LowStockAlertMail;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched by App\Console\Commands\CheckLowStock for one tenant at a
 * time. Takes variant *ids*, not models — SerializesModels re-fetch
 * pitfall, same as every other tenant-scoped job in this codebase
 * (GenerateInvoicePdfJob's own docblock has the full explanation).
 */
class NotifyLowStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param list<int> $variantIds
     */
    public function __construct(private readonly int $tenantId, private readonly array $variantIds)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null || $tenant->owner_id === null) {
            return;
        }

        $recipient = User::withoutTenantScope()->find($tenant->owner_id)?->email;

        if (!$recipient) {
            return;
        }

        $variants = ProductVariant::withoutTenantScope()
            ->whereIn('id', $this->variantIds)
            ->with('product:id,name')
            ->get();

        if ($variants->isEmpty()) {
            return;
        }

        Mail::to($recipient)->send(new LowStockAlertMail($variants));
    }
}
