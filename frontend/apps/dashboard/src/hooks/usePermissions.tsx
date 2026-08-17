import { type ReactNode, useCallback, useMemo } from 'react';

import { useDashboardAuthStore } from '../stores/dashboardAuthStore';

export interface UsePermissionsResult {
  roles: string[];
  permissions: string[];
  hasRole: (role: string) => boolean;
  can: (permission: string) => boolean;
  canAny: (permissionList: string[]) => boolean;
  canAll: (permissionList: string[]) => boolean;
}

/**
 * Mirrors the backend's spatie/laravel-permission surface (Phase 2.C) —
 * `can()` matches `App\Models\User::can()` exactly, including the 'owner'
 * role's `*` wildcard grant (config/permissions.php), since GET /auth/me
 * already expands that wildcard into the full permission list server-side
 * rather than sending the literal string '*'.
 */
export function usePermissions(): UsePermissionsResult {
  const roles = useDashboardAuthStore((state) => state.roles);
  const permissions = useDashboardAuthStore((state) => state.permissions);

  const hasRole = useCallback((role: string) => roles.includes(role), [roles]);
  const can = useCallback((permission: string) => permissions.includes(permission), [permissions]);
  const canAny = useCallback(
    (permissionList: string[]) => permissionList.some((permission) => permissions.includes(permission)),
    [permissions],
  );
  const canAll = useCallback(
    (permissionList: string[]) => permissionList.every((permission) => permissions.includes(permission)),
    [permissions],
  );

  return useMemo(
    () => ({ roles, permissions, hasRole, can, canAny, canAll }),
    [roles, permissions, hasRole, can, canAny, canAll],
  );
}

export interface CanProps {
  /** A single permission string, or a list — see `mode` for how a list is combined. */
  permission: string | string[];
  mode?: 'any' | 'all';
  fallback?: ReactNode;
  children: ReactNode;
}

/**
 * Declarative permission gate for JSX — `<Can permission="staff.invite">
 * <Button>Invite</Button></Can>`. Renders `fallback` (default nothing)
 * when the check fails. This governs *rendering* only; every endpoint it
 * guards is independently enforced server-side (UserPolicy et al.) — this
 * component is a UX convenience, never the actual security boundary.
 */
export function Can({ permission, mode = 'all', fallback = null, children }: CanProps) {
  const { can, canAny, canAll } = usePermissions();

  const allowed = Array.isArray(permission)
    ? mode === 'any'
      ? canAny(permission)
      : canAll(permission)
    : can(permission);

  return allowed ? <>{children}</> : <>{fallback}</>;
}
