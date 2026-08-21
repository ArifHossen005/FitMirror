<?php

namespace App\Services\Store;

use App\Enums\CustomDomainStatus;
use App\Models\CustomDomainRequest;
use App\Models\Tenant;
use App\Services\BaseService;
use App\Support\Dns\DnsResolver;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A tenant's claim on their own domain, verified by DNS TXT challenge.
 *
 * Ownership is proved before `tenants.custom_domain` is ever populated:
 * until verify() finds the token published at
 * `_fitmirror-verification.{domain}`, ResolveTenant will not answer on the
 * host, so a request naming a domain the tenant does not control is inert
 * rather than a hijack.
 *
 * The DNS lookup itself goes through the DnsResolver interface rather than
 * dns_get_record() directly — see that interface's docblock for why.
 */
class CustomDomainService extends BaseService
{
    /**
     * Hosts a tenant must never be able to claim, since holding one would
     * let them intercept FitMirror's own traffic or issue certificates in
     * FitMirror's name.
     *
     * @var list<string>
     */
    public const BLOCKED_SUFFIXES = ['fitmirror.com', 'fitmirror.io', 'localhost'];

    public function __construct(private readonly DnsResolver $dns) {}

    /**
     * Creates (or replaces) the tenant's pending domain request.
     *
     * A tenant has at most one custom domain, so requesting a second
     * replaces the first rather than accumulating claims — the old row is
     * deleted so its unique hold on that hostname is released for anyone
     * else who genuinely owns it.
     */
    public function request(Tenant $tenant, string $domain): CustomDomainRequest
    {
        $normalised = $this->normalise($domain);

        $this->assertDomainIsUsable($normalised, $tenant);

        return $this->transaction(function () use ($tenant, $normalised) {
            CustomDomainRequest::query()
                ->where('tenant_id', $tenant->id)
                ->where('domain', '!=', $normalised)
                ->delete();

            $request = CustomDomainRequest::query()->firstOrNew([
                'tenant_id' => $tenant->id,
                'domain' => $normalised,
            ]);

            if (!$request->exists) {
                // Token generated once, then kept across retries — see
                // CustomDomainStatus::isRetryable(). Rotating it would
                // invalidate the record the tenant has already pasted into
                // their DNS panel.
                $request->verification_token = CustomDomainRequest::generateVerificationToken();
                $request->status = CustomDomainStatus::Pending;
                $request->attempts = 0;
            }

            $request->save();

            return $request;
        });
    }

    public function current(Tenant $tenant): ?CustomDomainRequest
    {
        return CustomDomainRequest::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();
    }

    /**
     * Resolves the challenge record and, on a match, activates the domain.
     *
     * Never throws for "not published yet" — that is the expected answer
     * while DNS propagates. The request is marked Failed with the reason
     * recorded so the dashboard can show what was actually found, and the
     * tenant can retry with the same token.
     */
    public function verify(CustomDomainRequest $request): CustomDomainRequest
    {
        if ($request->status === CustomDomainStatus::Verified) {
            return $request;
        }

        $found = $this->dns->txtRecords($request->dnsRecordName());
        $matched = in_array($request->verification_token, $found, true);

        return $this->transaction(function () use ($request, $found, $matched) {
            $request->forceFill([
                'attempts' => $request->attempts + 1,
                'last_checked_at' => now(),
                'status' => $matched ? CustomDomainStatus::Verified->value : CustomDomainStatus::Failed->value,
                'verified_at' => $matched ? now() : null,
                'last_error' => $matched ? null : $this->describeMismatch($found),
            ])->save();

            if ($matched) {
                // Only now does the host become resolvable — this single
                // write is what makes ResolveTenant answer on the domain.
                $request->tenant->forceFill(['custom_domain' => $request->domain])->save();
            }

            return $request;
        });
    }

    /**
     * Withdraws the claim and stops serving the tenant on that host.
     */
    public function remove(CustomDomainRequest $request): void
    {
        $this->transaction(function () use ($request) {
            $tenant = $request->tenant;

            if ($tenant->custom_domain === $request->domain) {
                $tenant->forceFill(['custom_domain' => null])->save();
            }

            $request->delete();
        });
    }

    public function normalise(string $domain): string
    {
        $domain = Str::lower(trim($domain));
        // Tenants paste a URL as often as a hostname, so the scheme, any
        // path, and a trailing dot are all stripped rather than rejected.
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = Str::before($domain, '/');

        return rtrim($domain, '.');
    }

    private function assertDomainIsUsable(string $domain, Tenant $tenant): void
    {
        if (preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
            throw ValidationException::withMessages([
                'domain' => ['Enter a valid domain, for example shop.example.com.'],
            ]);
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if ($domain === $suffix || str_ends_with($domain, '.' . $suffix)) {
                throw ValidationException::withMessages([
                    'domain' => ['That domain is reserved by FitMirror.'],
                ]);
            }
        }

        // withoutTenantScope(): a competing claim by *another* tenant is
        // exactly what must be detected here, and the scoped query would
        // hide it — the tenant would only discover the conflict when the
        // unique index rejected the insert. A deliberate, narrow bypass in
        // the family described by Decision D-13.
        $claimedByAnother = CustomDomainRequest::withoutTenantScope()
            ->where('domain', $domain)
            ->where('tenant_id', '!=', $tenant->id)
            ->exists();

        $usedByAnother = Tenant::withTrashed()
            ->where('custom_domain', $domain)
            ->whereKeyNot($tenant->id)
            ->exists();

        if ($claimedByAnother || $usedByAnother) {
            throw ValidationException::withMessages([
                'domain' => ['That domain is already connected to another FitMirror account.'],
            ]);
        }
    }

    /**
     * @param list<string> $found
     */
    private function describeMismatch(array $found): string
    {
        if ($found === []) {
            return 'No TXT record found yet. DNS changes can take up to 24 hours to propagate.';
        }

        return 'A TXT record was found, but it did not match the expected verification token.';
    }
}
