<?php

namespace App\Services\Store;

use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Assigns and validates the `{slug}.fitmirror.com` subdomain a tenant is
 * reachable on.
 *
 * `tenants.subdomain` and `tenants.slug` are separate columns and stay in
 * lockstep here: ResolveTenant matches an incoming host against `slug`
 * (see its resolveFromSubdomain()), while `subdomain` is what the
 * dashboard displays and what the unique index protects. Writing one
 * without the other would produce a tenant whose displayed address does
 * not resolve — so this service is the only place either is changed.
 */
class SubdomainService extends BaseService
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 63;

    /**
     * Labels that must never belong to a tenant, either because the
     * platform itself serves them or because owning one would let a tenant
     * impersonate FitMirror to their own customers.
     *
     * @var list<string>
     */
    public const RESERVED = [
        'www', 'api', 'admin', 'app', 'mail', 'smtp', 'imap', 'ftp', 'ns1', 'ns2',
        'mission', 'mission-control', 'kiosk', 'portal', 'dashboard', 'static',
        'cdn', 'assets', 'media', 'files', 'download', 'status', 'support', 'help',
        'billing', 'pay', 'payment', 'checkout', 'login', 'signup', 'register',
        'fitmirror', 'test', 'staging', 'dev', 'demo', 'blog', 'docs', 'shop',
    ];

    /**
     * Whether $subdomain is well-formed, unreserved, and free — the answer
     * behind the dashboard's live availability check as the owner types.
     *
     * @return array{available: bool, subdomain: string, reason: string|null, url: string|null}
     */
    public function check(string $subdomain, ?Tenant $ignoreTenant = null): array
    {
        $normalised = $this->normalise($subdomain);
        $reason = $this->rejectionReason($normalised, $ignoreTenant);

        return [
            'available' => $reason === null,
            'subdomain' => $normalised,
            'reason' => $reason,
            'url' => $reason === null ? $this->urlFor($normalised) : null,
        ];
    }

    /**
     * @return array{subdomain: string, url: string}
     */
    public function assign(Tenant $tenant, string $subdomain): array
    {
        $normalised = $this->normalise($subdomain);
        $reason = $this->rejectionReason($normalised, $tenant);

        if ($reason !== null) {
            throw ValidationException::withMessages(['subdomain' => [$reason]]);
        }

        return $this->transaction(function () use ($tenant, $normalised) {
            // Both columns, always together — see this class's docblock.
            $tenant->forceFill([
                'subdomain' => $normalised,
                'slug' => $normalised,
            ])->save();

            return ['subdomain' => $normalised, 'url' => $this->urlFor($normalised)];
        });
    }

    public function urlFor(string $subdomain): string
    {
        $root = config('app.tenant_root_domain');

        // No root domain configured (the local dev default) means there is
        // no subdomain URL to build — every app runs on a bare localhost
        // port and resolves the tenant from the X-Tenant header instead
        // (see ResolveTenant). Returning the frontend origin keeps the
        // dashboard's "your address" field honest rather than showing a
        // hostname that resolves nowhere.
        if (empty($root)) {
            return rtrim((string) config('app.frontend_url'), '/');
        }

        return "https://{$subdomain}.{$root}";
    }

    public function normalise(string $subdomain): string
    {
        return Str::lower(trim($subdomain));
    }

    /**
     * The single reason a subdomain is unusable, or null when it is fine.
     * Returns one message rather than a list so the availability endpoint
     * and the assignment endpoint present identical wording.
     */
    private function rejectionReason(string $subdomain, ?Tenant $ignoreTenant): ?string
    {
        if (strlen($subdomain) < self::MIN_LENGTH) {
            return 'Must be at least ' . self::MIN_LENGTH . ' characters.';
        }

        if (strlen($subdomain) > self::MAX_LENGTH) {
            return 'Must be at most ' . self::MAX_LENGTH . ' characters.';
        }

        // RFC 1123 label: lowercase alphanumerics and hyphens, never
        // starting or ending with a hyphen.
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain) !== 1) {
            return 'Use lowercase letters, numbers and hyphens only, starting and ending with a letter or number.';
        }

        // Two hyphens in positions 3-4 is the IDNA "xn--" punycode prefix
        // shape; allowing it would let a tenant register a homograph of a
        // real brand that browsers render in a non-Latin script.
        if (str_starts_with(substr($subdomain, 2, 2), '--')) {
            return 'That prefix is reserved.';
        }

        if (in_array($subdomain, self::RESERVED, true)) {
            return 'That address is reserved.';
        }

        // withTrashed(): the unique indexes on tenants.subdomain/slug span
        // soft-deleted rows, so a suspended-and-deleted tenant still holds
        // its address. Ignoring that here would turn a clean field-level
        // message into a duplicate-key 500 at save time.
        $taken = Tenant::withTrashed()
            ->where(function ($query) use ($subdomain) {
                $query->where('subdomain', $subdomain)->orWhere('slug', $subdomain);
            })
            ->when($ignoreTenant !== null, fn ($query) => $query->whereKeyNot($ignoreTenant->id))
            ->exists();

        return $taken ? 'That address is already taken.' : null;
    }
}
