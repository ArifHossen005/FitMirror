<?php

/**
 * The tenant-side permission matrix (module x action), independent of
 * whether the module's underlying tables exist yet — this is a declarative
 * map of every capability the product will eventually gate, consumed today
 * by RolePermissionSeeder (Owner/Manager/Staff) and, going forward, by
 * every new controller action's authorize() call as each module lands.
 *
 * Permission name shape: "{module}.{action}", e.g. "staff.invite". Modules
 * whose tables don't exist yet (products, campaigns, loyalty, customers,
 * reports, stores) still get real Permission rows seeded now, so a
 * controller built in a later phase only has to reference the string, not
 * invent and re-seed it.
 *
 * See DOCUMENTATION.md Section 4.4.5 for the full rationale, including why
 * Mission Control's Super Admin/Support/Finance roles are *not* modelled
 * here (Decision D-15).
 */
return [

    'modules' => [
        'dashboard' => ['view'],
        'tenant_settings' => ['view', 'update'],
        'staff' => ['view', 'invite', 'update', 'deactivate', 'delete'],
        'stores' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete', 'publish'],
        'categories' => ['view', 'create', 'update', 'delete'],
        'campaigns' => ['view', 'create', 'update', 'delete', 'publish'],
        'loyalty' => ['view', 'manage'],
        'customers' => ['view', 'create', 'update', 'delete', 'export'],
        'reports' => ['view', 'export'],
        'billing' => ['view', 'manage'],
        'kiosk' => ['view', 'manage'],
        'audit_log' => ['view'],
    ],

    /**
     * Default permission grants per seeded tenant role. Built from
     * 'modules' above at seed time (RolePermissionSeeder), not hand-listed
     * here, so the two can never drift out of sync — a module/action added
     * above is automatically available to assign, even if no role gets it
     * by default yet.
     *
     * Role shape, by product intent:
     *   - owner:   every permission that exists. The tenant's own account;
     *              can never be demoted or deleted via the staff API.
     *   - manager: everything an Owner can do *inside* day-to-day
     *              operations, excluding billing changes, tenant settings,
     *              and staff deletion — a Manager can invite/update/
     *              deactivate staff, but only the Owner can hard-delete an
     *              account or touch the subscription.
     *   - staff:   read-only across catalog/customer/report modules, plus
     *              the kiosk view needed to run try-on sessions. No create/
     *              update/delete anywhere, no billing or settings access.
     */
    'role_permissions' => [
        'owner' => ['*'],

        'manager' => [
            'dashboard.view',
            'staff.view', 'staff.invite', 'staff.update', 'staff.deactivate',
            'stores.view', 'stores.create', 'stores.update',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.publish',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.delete', 'campaigns.publish',
            'loyalty.view', 'loyalty.manage',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.export',
            'reports.view', 'reports.export',
            'kiosk.view', 'kiosk.manage',
            'audit_log.view',
        ],

        'staff' => [
            'dashboard.view',
            'products.view',
            'categories.view',
            'campaigns.view',
            'loyalty.view',
            'customers.view',
            'reports.view',
            'kiosk.view',
        ],
    ],
];
