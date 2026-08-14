<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for every job that must run scoped to a tenant (media
 * processing, analytics rollups, notification dispatch, ...). The tenant
 * is captured at dispatch time and serialized onto the queue payload like
 * any other constructor property, then TenantContext is set for the
 * duration of handle() and unconditionally forgotten afterward — a plain
 * `queue:work` process reuses one container across many jobs (see
 * TenantContext's own docblock), so a job that forgot to clean up after
 * itself would leak its tenant into whatever job runs next.
 *
 * Concrete jobs implement handleForTenant(Tenant $tenant) instead of
 * handle().
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant) {}

    final public function handle(): void
    {
        $context = app(TenantContext::class);

        $context->set($this->tenant);

        try {
            $this->handleForTenant($this->tenant);
        } finally {
            $context->forget();
        }
    }

    abstract protected function handleForTenant(Tenant $tenant): void;
}
