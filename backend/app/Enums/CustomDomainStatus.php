<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 *
 * Tracks one tenant's request to serve FitMirror from their own domain.
 * Verification is a DNS TXT challenge (see
 * App\Services\Store\CustomDomainService) — the tenant publishes a token
 * FitMirror generated, FitMirror resolves it, and only then is
 * `tenants.custom_domain` populated so ResolveTenant will answer on that
 * host.
 */
enum CustomDomainStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting DNS Verification',
            self::Verified => 'Verified',
            self::Failed => 'Verification Failed',
        };
    }

    /**
     * Failed is deliberately retryable rather than terminal — the usual
     * cause is DNS propagation delay, not a wrong token, and forcing the
     * tenant to delete and re-create the request would rotate the token
     * they have already pasted into their DNS panel.
     */
    public function isRetryable(): bool
    {
        return $this !== self::Verified;
    }
}
