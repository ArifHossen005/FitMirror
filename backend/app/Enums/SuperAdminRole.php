<?php

namespace App\Enums;

/**
 * The three Mission Control roles from the product document — seeded in
 * Phase 2.C (RolePermissionSeeder), referenced here so the guard/middleware
 * layer built in Phase 1.C has a real permission mapping to enforce against
 * instead of a placeholder.
 */
enum SuperAdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Support = 'support';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Support => 'Support Agent',
            self::Finance => 'Finance',
        };
    }

    /**
     * @return list<SuperAdminPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Full platform control — the only role that can approve/reject
            // tenants, edit plans, and manage other Mission Control accounts.
            self::SuperAdmin => SuperAdminPermission::cases(),

            // Handles the tenant approval queue and support tickets, but
            // cannot touch plan pricing or billing/refunds.
            self::Support => [SuperAdminPermission::Tenants, SuperAdminPermission::Support],

            // Billing, refunds, invoices, and plan pricing only.
            self::Finance => [SuperAdminPermission::Billing, SuperAdminPermission::Plans],
        };
    }
}
