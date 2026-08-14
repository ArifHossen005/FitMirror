<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Registered via Sanctum::usePersonalAccessTokenModel() in
 * AppServiceProvider::boot(). Authentication must resolve *before* a
 * tenant context can exist — TenantScope's fail-closed default (see
 * App\Scopes\TenantScope) would otherwise make every Sanctum-authenticated
 * request against a BelongsToTenant model (App\Models\User) resolve to a
 * null tokenable, since no tenant is known yet at the point the token
 * itself is being resolved. Explicitly bypassing the scope here — once,
 * centrally — is what makes `auth:sanctum` work at all for tenant users,
 * rather than every controller needing to know about this.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function tokenable(): MorphTo
    {
        return parent::tokenable()->withoutGlobalScope(TenantScope::class);
    }
}
