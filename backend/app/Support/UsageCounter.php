<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Redis-backed daily usage counters — try-on sessions today, SMS sent
 * today, etc. Keys are *not* date-partitioned (`usage:{tenant_id}:
 * {metric}`, not `usage:{tenant_id}:{metric}:{date}`); a single scheduled
 * job (`routes/console.php`, `usage:reset`) wipes every counter at
 * midnight Asia/Dhaka instead — see that command's own docblock for why
 * that's a deliberate choice over per-key TTLs.
 */
class UsageCounter
{
    private const KEY_PREFIX = 'usage';

    public function increment(Tenant $tenant, string $metric, int $by = 1): int
    {
        return (int) Redis::connection('default')->incrby($this->key($tenant, $metric), $by);
    }

    public function current(Tenant $tenant, string $metric): int
    {
        return (int) (Redis::connection('default')->get($this->key($tenant, $metric)) ?? 0);
    }

    public function reset(Tenant $tenant, string $metric): void
    {
        Redis::connection('default')->del($this->key($tenant, $metric));
    }

    /**
     * Deletes every tenant's usage counter for every metric — the actual
     * work behind the `usage:reset` scheduled command. Uses SCAN (cursor-
     * based), never KEYS, so this never blocks the shared Redis instance
     * even with a large number of tenants x metrics.
     *
     * Two verified phpredis quirks this depends on (confirmed against the
     * real dev Redis instance, not assumed from docs):
     *   1. The scan cursor MUST start as `null`, not `0` or `'0'` — either
     *      of those makes phpredis's `scan()` return `false` immediately,
     *      even with matching keys present.
     *   2. `SCAN`'s `match` pattern operates on the *raw* keyspace and so
     *      must include the connection's configured key prefix
     *      (`config('database.redis.options.prefix')`), but `DEL` re-adds
     *      that same prefix automatically — passing an already-prefixed
     *      key to `del()` silently deletes nothing, so the prefix must be
     *      stripped back off the keys `scan()` returns before calling
     *      `del()` on them.
     */
    public function resetAll(): int
    {
        $connection = Redis::connection('default');
        $prefix = (string) config('database.redis.options.prefix', '');
        $pattern = $prefix . self::KEY_PREFIX . ':*';

        $deleted = 0;
        $cursor = null;

        do {
            [$cursor, $keys] = $this->scanBatch($connection, $cursor, $pattern);

            if (!empty($keys)) {
                $unprefixed = array_map(fn (string $key) => Str::after($key, $prefix), $keys);
                $deleted += (int) $connection->del($unprefixed);
            }
        } while (!empty($cursor));

        return $deleted;
    }

    /**
     * Isolates the one call that depends on phpredis mutating $cursor
     * by reference — a native-extension behaviour PHPStan's stubs don't
     * model, so tracking that mutation across loop iterations directly in
     * resetAll() reads to static analysis as "$cursor is always null".
     * Returning the post-call cursor explicitly instead makes the
     * exchange an ordinary value return, which PHPStan tracks correctly.
     *
     * Two verified phpredis quirks this depends on (confirmed against the
     * real dev Redis instance, not assumed from docs):
     *   1. The scan cursor MUST start as `null`, not `0` or `'0'` — either
     *      makes phpredis's `scan()` return `false` immediately, even
     *      with matching keys present.
     *   2. `SCAN`'s `match` pattern operates on the *raw* keyspace and so
     *      must include the connection's configured key prefix
     *      (`config('database.redis.options.prefix')`), but `DEL` re-adds
     *      that same prefix automatically — passing an already-prefixed
     *      key to `del()` silently deletes nothing, so the prefix must be
     *      stripped back off the keys `scan()` returns before calling
     *      `del()` on them (handled by the caller, resetAll()).
     *
     * @return array{0: int|null, 1: list<string>}
     */
    private function scanBatch(Connection $connection, mixed $cursor, string $pattern): array
    {
        $keys = $connection->client()->scan($cursor, $pattern, 500);

        return [$cursor === false ? null : $cursor, $keys === false ? [] : $keys];
    }

    private function key(Tenant $tenant, string $metric): string
    {
        return self::KEY_PREFIX . ":{$tenant->id}:{$metric}";
    }
}
