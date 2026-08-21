<?php

namespace App\Models;

use App\Enums\CustomDomainStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomDomainRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A tenant's claim on their own domain, pending a DNS TXT challenge.
 *
 * The record the tenant must publish is
 * `_fitmirror-verification.{domain} TXT "{verification_token}"`. Only once
 * App\Services\Store\CustomDomainService resolves that value does
 * `tenants.custom_domain` get populated — until then ResolveTenant will
 * not answer on the host, so an unverified claim on someone else's domain
 * is inert.
 */
class CustomDomainRequest extends Model
{
    /** @use HasFactory<CustomDomainRequestFactory> */
    use BelongsToTenant, HasFactory;

    /** The subdomain label the TXT record is published under. */
    public const DNS_RECORD_PREFIX = '_fitmirror-verification';

    protected $fillable = [
        'tenant_id',
        'domain',
        'verification_token',
        'status',
        'verified_at',
        'last_checked_at',
        'last_error',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomDomainStatus::class,
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** The fully-qualified hostname the tenant must create the TXT record on. */
    public function dnsRecordName(): string
    {
        return self::DNS_RECORD_PREFIX . '.' . $this->domain;
    }

    /**
     * Copy-pasteable instructions for the tenant's DNS panel, returned by
     * the API so the dashboard never has to re-derive the record shape and
     * risk showing something the verifier does not actually look for.
     *
     * @return array{type: string, name: string, value: string, ttl: int}
     */
    public function dnsInstructions(): array
    {
        return [
            'type' => 'TXT',
            'name' => $this->dnsRecordName(),
            'value' => $this->verification_token,
            'ttl' => 300,
        ];
    }

    public static function generateVerificationToken(): string
    {
        return 'fitmirror-verify-' . Str::lower(Str::random(32));
    }
}
