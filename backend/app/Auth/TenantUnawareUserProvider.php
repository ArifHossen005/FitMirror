<?php

namespace App\Auth;

use App\Scopes\TenantScope;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered as the `users` auth provider's driver (config/auth.php) in
 * place of Laravel's default `eloquent` driver. Every internal auth
 * mechanism that resolves a User by a unique credential — the password
 * reset broker (Illuminate\Auth\Passwords\PasswordBroker) — does so before
 * any tenant is known, exactly like LoginService's own lookup (see that
 * class's docblock). Without this, `Password::sendResetLink()` and
 * `Password::reset()` silently fail to find any user at all, since
 * TenantScope's fail-closed default (App\Scopes\TenantScope) blocks the
 * underlying query.
 */
class TenantUnawareUserProvider extends EloquentUserProvider
{
    /**
     * @param Model|null $model
     * @return Builder<Model>
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
