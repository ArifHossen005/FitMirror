<?php

namespace App\Services\Catalog;

use App\Enums\CategoryGender;
use App\Models\Category;
use App\Models\Occasion;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\BaseService;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/**
 * Seeds a tenant's starter catalog taxonomy — the default Bangladeshi
 * apparel categories, occasions, and tags PROGRESS.md's 5.A checklist asks
 * for. Deliberately not wired into RegistrationService's automatic
 * tenant-provisioning flow: that flow (Phase 2.A) is itself still an open
 * item, explicitly deferred pending `stores`/roles (see PROGRESS.md's
 * "Create tenant provisioning service" note under 2.A) — the same
 * blocked-by-a-later-phase shape applies here, so this is invoked
 * on-demand via `php artisan catalog:seed-defaults {tenant}` for now
 * (App\Console\Commands\SeedCatalogDefaults) and will be folded into
 * automatic provisioning once that service itself is built. Idempotent —
 * every insert is a `firstOrCreate` keyed on the seeded slug, so running it
 * twice for the same tenant never duplicates rows.
 */
class CatalogTaxonomyService extends BaseService
{
    /**
     * @var array<string, list<string>>
     */
    private const CATEGORY_TREE = [
        'boys' => ['Panjabi', 'Shirt', 'T-Shirt', 'Pant', 'Coat', 'Jacket'],
        'girls' => ['Saree', 'Threepiece', 'Kurti', 'Orna', 'Frock', 'Lehenga'],
    ];

    /**
     * @var list<string>
     */
    private const OCCASIONS = ['Wedding', 'Eid', 'Office', 'Casual', 'Party'];

    /**
     * @var list<string>
     */
    private const TAGS = ['New Arrival', 'Bestseller', 'Eid Special', 'Sale'];

    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Runs under TenantContext::runAs() — this is invoked from an artisan
     * command, which (unlike a web request) has no ResolveTenant middleware
     * to set an active tenant context first. Without it, TenantScope's
     * fail-closed rule (D-13) would make every firstOrCreate() lookup below
     * see zero rows regardless of what already exists, breaking the
     * idempotency this class's own docblock promises — each rerun would
     * attempt to re-insert the same slug and hit a unique-constraint error
     * instead of finding and skipping it.
     */
    public function seed(Tenant $tenant): void
    {
        $this->tenantContext->runAs($tenant, function () use ($tenant) {
            $this->transaction(function () use ($tenant) {
                $this->seedCategories($tenant);
                $this->seedOccasions($tenant);
                $this->seedTags($tenant);
            });
        });
    }

    private function seedCategories(Tenant $tenant): void
    {
        foreach (self::CATEGORY_TREE as $genderKey => $children) {
            $gender = CategoryGender::from($genderKey);

            $parent = Category::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => Str::slug($gender->label())],
                ['name' => $gender->label(), 'gender' => $gender->value, 'sort_order' => 0],
            );

            foreach ($children as $index => $name) {
                Category::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => Str::slug($gender->label() . '-' . $name)],
                    [
                        'name' => $name,
                        'parent_id' => $parent->id,
                        'gender' => $gender->value,
                        'sort_order' => $index,
                    ],
                );
            }
        }
    }

    private function seedOccasions(Tenant $tenant): void
    {
        foreach (self::OCCASIONS as $index => $name) {
            Occasion::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index],
            );
        }
    }

    private function seedTags(Tenant $tenant): void
    {
        foreach (self::TAGS as $name) {
            Tag::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
