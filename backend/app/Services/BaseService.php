<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base class for the service layer. See DOCUMENTATION.md §4.4 "Service &
 * Query-Builder Conventions" for the full rationale (Decision D-09) —
 * controllers stay thin and delegate multi-step or transactional business
 * logic to a Service; simple single-model reads/writes go straight through
 * Eloquent in the controller and don't need a service at all.
 */
abstract class BaseService
{
    /**
     * Runs $callback inside a DB transaction and rethrows on failure. Thin
     * wrapper over DB::transaction() so every service commits/rolls back
     * the same way instead of each one reaching for the facade directly.
     *
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     * @return TReturn
     *
     * @throws Throwable
     */
    protected function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
