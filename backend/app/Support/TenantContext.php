<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;

/**
 * The single source of truth for "which tenant is this request/job acting
 * as". Bound as a container singleton (AppServiceProvider::register()), so
 * ResolveTenant middleware sets it once per request and every
 * BelongsToTenant-scoped model query consults the same instance via
 * app(TenantContext::class).
 *
 * Queue workers (`php artisan queue:work`) reuse one process — and one
 * container — across many jobs, so nothing may assume this resets between
 * requests automatically. TenantAwareJob (app/Jobs/TenantAwareJob.php)
 * explicitly set()s and forget()s around every job's handle() to prevent a
 * previous job's tenant leaking into the next one.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Runs $callback with $tenant temporarily active, restoring whatever
     * context existed beforehand — even if $callback throws. Used for
     * cross-tenant operations that must run as a specific tenant
     * (Mission Control impersonation, admin tooling, scheduled jobs that
     * iterate every tenant).
     *
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     * @return TReturn
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
