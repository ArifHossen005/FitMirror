<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Widget;
use Tests\TestCase;

/**
 * Proves BelongsToTenant/TenantScope actually make cross-tenant leakage
 * impossible — not just "the code compiles". Uses a throwaway `widgets`
 * table (tests/Fixtures/Widget.php) since no real tenant-owned table exists
 * yet in Phase 2.A.
 */
class TenantScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('widgets');

        parent::tearDown();
    }

    public function test_a_new_widget_is_auto_assigned_the_active_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        app(TenantContext::class)->runAs($tenant, function () use ($tenant) {
            $widget = Widget::create(['name' => 'Auto-assigned']);

            $this->assertSame($tenant->id, $widget->tenant_id);
        });
    }

    public function test_a_tenant_can_only_see_its_own_widgets(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $context = app(TenantContext::class);

        $context->runAs($tenantA, fn () => Widget::create(['name' => 'Belongs to A']));
        $context->runAs($tenantB, fn () => Widget::create(['name' => 'Belongs to B']));

        $context->runAs($tenantA, function () {
            $visible = Widget::all();

            $this->assertCount(1, $visible);
            $this->assertSame('Belongs to A', $visible->first()->name);
        });

        $context->runAs($tenantB, function () {
            $visible = Widget::all();

            $this->assertCount(1, $visible);
            $this->assertSame('Belongs to B', $visible->first()->name);
        });
    }

    public function test_find_cannot_reach_across_a_tenant_boundary(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $context = app(TenantContext::class);

        $widgetInB = $context->runAs($tenantB, fn () => Widget::create(['name' => 'Belongs to B']));

        $context->runAs($tenantA, function () use ($widgetInB) {
            $this->assertNull(Widget::find($widgetInB->id));
        });
    }

    public function test_without_any_tenant_context_no_widgets_are_visible(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->runAs($tenant, fn () => Widget::create(['name' => 'Belongs to someone']));

        // No TenantContext active in this test process by default — mirrors
        // a console command or unscoped background process.
        $this->assertCount(0, Widget::all());
    }

    public function test_without_tenant_scope_reaches_across_every_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $context = app(TenantContext::class);

        $context->runAs($tenantA, fn () => Widget::create(['name' => 'Belongs to A']));
        $context->runAs($tenantB, fn () => Widget::create(['name' => 'Belongs to B']));

        $context->runAs($tenantA, function () {
            $this->assertCount(2, Widget::withoutTenantScope()->get());
        });
    }
}
