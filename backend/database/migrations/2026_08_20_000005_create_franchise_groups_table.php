<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_groups', function (Blueprint $table) {
            $table->id();
            // The tenant that owns the group and sees the consolidated
            // view. Carries `tenant_id` like every other tenant-owned
            // table (BelongsToTenant, DOCUMENTATION.md §4.4.1) — the
            // *members* are a separate many-to-many below, so a franchisor
            // owning a group is not the same relation as a franchisee
            // belonging to one.
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('franchise_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_group_id')->constrained()->cascadeOnDelete();
            // Deliberately NOT named tenant_id and deliberately without
            // BelongsToTenant on the model: this column is the *member*
            // tenant, a different tenant from the group's owner. Naming it
            // tenant_id would make TenantScope silently filter the
            // franchisor's own membership list down to itself.
            $table->foreignId('member_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Explicitly named: the auto-generated name would be 66 characters,
            // past MySQL's 64-character identifier limit.
            $table->unique(['franchise_group_id', 'member_tenant_id'], 'franchise_group_members_group_member_unique');
            $table->index('member_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_group_members');
        Schema::dropIfExists('franchise_groups');
    }
};
