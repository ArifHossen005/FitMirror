<?php

namespace App\Support\Dns;

/**
 * Production DnsResolver, backed by PHP's own resolver (`dns_get_record`,
 * which uses the host's configured nameservers).
 *
 * dns_get_record() emits a PHP warning and returns false for an
 * unresolvable name rather than throwing, so the warning is suppressed and
 * the false converted to an empty array — an unpublished record is the
 * normal state of a domain the tenant has only just requested, not an
 * exceptional one (see the interface's own contract).
 */
class SystemDnsResolver implements DnsResolver
{
    /**
     * @return list<string>
     */
    public function txtRecords(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_TXT);

        if ($records === false) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            // PHP exposes a TXT record's value as `txt` (the joined
            // string) and `entries` (the raw character-strings a single
            // record may be split into — a TXT value longer than 255 bytes
            // is chunked on the wire). Both are read so a token that was
            // chunked by the tenant's DNS provider still matches.
            if (isset($record['txt']) && is_string($record['txt'])) {
                $values[] = $record['txt'];
            }

            if (isset($record['entries']) && is_array($record['entries'])) {
                $joined = implode('', array_filter($record['entries'], 'is_string'));

                if ($joined !== '') {
                    $values[] = $joined;
                }
            }
        }

        return array_values(array_unique($values));
    }
}
