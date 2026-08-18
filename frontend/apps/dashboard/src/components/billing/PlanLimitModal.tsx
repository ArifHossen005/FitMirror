import { Button, Modal } from '@fitmirror/ui';
import { useNavigate } from 'react-router-dom';

import type { PlanLimitErrorDetails } from '../../lib/billing';

function formatLimitLabel(key: string): string {
  return key
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

export interface PlanLimitModalProps {
  isOpen: boolean;
  onClose: () => void;
  details: PlanLimitErrorDetails | null;
}

/**
 * Shown whenever an action fails with `plan_limit_exceeded` (see
 * `parsePlanLimitError` in lib/billing.ts) — any page that creates a
 * countable, plan-gated resource (staff invites today; categories/SKUs/
 * branches once those modules exist) can catch its mutation's error and
 * render this instead of a bare toast.
 */
export function PlanLimitModal({ isOpen, onClose, details }: PlanLimitModalProps) {
  const navigate = useNavigate();

  if (!details) return null;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Plan limit reached">
      <p className="text-sm text-neutral-600">
        You've used {details.current} of {details.max} available for{' '}
        <strong>{formatLimitLabel(details.limit)}</strong> on your current plan.
      </p>
      <p className="mt-2 text-sm text-neutral-600">Upgrade your plan to raise this limit.</p>
      <div className="mt-6 flex justify-end gap-2">
        <Button variant="outline" onClick={onClose}>
          Not now
        </Button>
        <Button
          onClick={() => {
            onClose();
            navigate('/pricing');
          }}
        >
          Upgrade Plan
        </Button>
      </div>
    </Modal>
  );
}
