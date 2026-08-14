<?php

namespace App\Enums;

/**
 * One case per Mission Control module a route can gate behind. Consumed by
 * SuperAdminRole::permissions() and, from Phase 13 onward, an
 * EnsureSuperAdminPermission middleware applied per-route
 * (e.g. ->middleware('super_admin.permission:billing')).
 */
enum SuperAdminPermission: string
{
    case Tenants = 'tenants';
    case Plans = 'plans';
    case Billing = 'billing';
    case Ops = 'ops';
    case Support = 'support';
}
