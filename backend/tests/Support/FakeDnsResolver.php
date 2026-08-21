<?php

namespace Tests\Support;

use App\Support\Dns\DnsResolver;

/**
 * In-memory DnsResolver for tests — bound in place of SystemDnsResolver so
 * custom-domain verification can be exercised without a real lookup whose
 * answer depends on propagation. See the DnsResolver interface's own
 * docblock for why that seam exists at all.
 */
class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, list<string>> */
    private array $records = [];

    /** @var list<string> */
    public array $queried = [];

    /**
     * @param list<string> $values
     */
    public function publish(string $hostname, array $values): void
    {
        $this->records[$hostname] = $values;
    }

    /**
     * @return list<string>
     */
    public function txtRecords(string $hostname): array
    {
        $this->queried[] = $hostname;

        return $this->records[$hostname] ?? [];
    }
}
