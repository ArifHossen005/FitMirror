<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Catalog\CatalogTaxonomyService;
use Illuminate\Console\Command;

/**
 * `php artisan catalog:seed-defaults {tenant}` — seeds the default
 * Bangladeshi apparel category tree, occasions, and tags for one tenant.
 * See CatalogTaxonomyService's own docblock for why this is a manual
 * command rather than automatic at registration.
 */
class SeedCatalogDefaults extends Command
{
    protected $signature = 'catalog:seed-defaults {tenant : The tenant ID to seed}';

    protected $description = "Seed a tenant's default catalog taxonomy (categories, occasions, tags)";

    public function handle(CatalogTaxonomyService $taxonomy): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $taxonomy->seed($tenant);

        $this->info("Seeded default catalog taxonomy for tenant #{$tenant->id}.");

        return self::SUCCESS;
    }
}
