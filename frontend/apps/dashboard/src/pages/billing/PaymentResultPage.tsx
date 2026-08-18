import { Button } from '@fitmirror/ui';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';

const COPY: Record<string, { title: string; body: string; tone: string }> = {
  success: {
    title: 'Payment received',
    body: 'Thanks — your payment was verified. Your subscription is now awaiting FitMirror\'s approval before it activates.',
    tone: 'text-success-600',
  },
  failed: {
    title: 'Payment failed',
    body: 'SSLCommerz could not complete this payment. No charge was made — you can try again from your billing page.',
    tone: 'text-danger-600',
  },
  cancelled: {
    title: 'Payment cancelled',
    body: 'You cancelled the checkout before it completed. No charge was made.',
    tone: 'text-neutral-600',
  },
};

/**
 * `/billing/payment/:status` — where PaymentSuccessCallbackController /
 * PaymentFailCallbackController / PaymentCancelCallbackController
 * (backend, Phase 3.C) redirect the browser to after SSLCommerz returns
 * control. `:status` is one of success/failed/cancelled, matching those
 * controllers' own `resultUrl()` helpers exactly.
 */
export function PaymentResultPage() {
  const { status = 'failed' } = useParams<{ status: string }>();
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const invoice = params.get('invoice');
  const copy = COPY[status] ?? COPY.failed;

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 text-center shadow-sm">
        <h1 className={`text-xl font-semibold ${copy?.tone}`}>{copy?.title}</h1>
        <p className="mt-3 text-sm text-neutral-600">{copy?.body}</p>
        {invoice && <p className="mt-2 text-xs text-neutral-400">Invoice {invoice}</p>}
        <Button className="mt-6" onClick={() => navigate('/billing')}>
          Go to Billing
        </Button>
      </div>
    </div>
  );
}
