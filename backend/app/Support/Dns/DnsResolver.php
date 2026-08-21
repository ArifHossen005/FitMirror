<?php

namespace App\Support\Dns;

/**
 * The one seam between FitMirror and the public DNS system.
 *
 * An interface rather than a direct dns_get_record() call in the service,
 * for the same reason SslCommerzService's HTTP calls go through Laravel's
 * Http facade: domain verification must be testable without a real,
 * network-dependent lookup whose answer changes with propagation. Tests
 * bind FakeDnsResolver; every other environment gets SystemDnsResolver.
 */
interface DnsResolver
{
    /**
     * Every TXT value published at $hostname, or an empty array when the
     * name does not resolve or holds no TXT records.
     *
     * Implementations must not throw on an unresolvable name — "no such
     * record" is the expected answer while a tenant is still waiting for
     * DNS to propagate, not an error condition.
     *
     * @return list<string>
     */
    public function txtRecords(string $hostname): array;
}
