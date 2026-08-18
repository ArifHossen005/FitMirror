import { Button } from '@fitmirror/ui';
import type { ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';

import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

export interface FeatureLockedOverlayProps {
  /** A `plan_features` key, e.g. `campaign_manager` — see config/permissions.php's module list and PlanFeature's own docblock for the current set. */
  featureKey: string;
  label: string;
  children: ReactNode;
}

/**
 * Wraps a feature's real UI and blurs/locks it when the tenant's resolved
 * plan doesn't include it — reads `features` straight off GET /auth/me
 * (Phase 3.D addition, dashboardAuthStore), so it reflects the exact same
 * data App\Services\Plan\FeatureGate checks server-side. This governs
 * *rendering* only, the same disclaimer as the `<Can>` permission gate —
 * the real boundary is EnforcePlanFeature/FeatureGate on the API route.
 */
export function FeatureLockedOverlay({ featureKey, label, children }: FeatureLockedOverlayProps) {
  const navigate = useNavigate();
  const feature = useDashboardAuthStore((state) => state.features[featureKey]);

  if (feature?.enabled) {
    return <>{children}</>;
  }

  return (
    <div className="relative">
      <div className="pointer-events-none select-none opacity-40 blur-[2px]" aria-hidden="true">
        {children}
      </div>
      <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-lg bg-white/60 p-6 text-center">
        <p className="text-sm font-medium text-neutral-700">{label} isn't included in your current plan.</p>
        <Button size="sm" onClick={() => navigate('/pricing')}>
          Upgrade to unlock
        </Button>
      </div>
    </div>
  );
}
