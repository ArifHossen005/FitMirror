<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Resolves storage paths under the `tenant` disk (config/filesystems.php)
 * so every tenant's media lives in its own prefix — `tenants/{id}/...` —
 * regardless of whether the disk driver is local (dev) or S3/R2
 * (production). No tenant ever writes to another tenant's prefix because
 * no code path ever has a reason to construct a path any other way.
 */
class TenantStorage
{
    public static function path(Tenant|int $tenant, string $path = ''): string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $trimmedPath = ltrim($path, '/');

        return $trimmedPath === ''
            ? "tenants/{$tenantId}"
            : "tenants/{$tenantId}/{$trimmedPath}";
    }
}
