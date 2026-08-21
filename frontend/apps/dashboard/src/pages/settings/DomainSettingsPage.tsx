import { Button, Input } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';

import { FeatureLockedOverlay } from '../../components/billing/FeatureLockedOverlay';
import {
  ApiError,
  assignSubdomain,
  checkSubdomain,
  fetchCustomDomain,
  fetchSubdomain,
  removeCustomDomain,
  requestCustomDomain,
  type SubdomainCheck,
  verifyCustomDomain,
} from '../../lib/stores';

/** How long to wait after the last keystroke before asking the API. */
const CHECK_DEBOUNCE_MS = 400;

/**
 * The two addresses a shop can be reached at: the FitMirror subdomain
 * every tenant gets, and (on plans that include it) their own domain,
 * verified by a DNS TXT challenge.
 *
 * The DNS record shown here comes straight from the API rather than being
 * rebuilt client-side, so what the owner is told to publish and what the
 * verifier actually looks for cannot drift apart.
 */
export function DomainSettingsPage() {
  const queryClient = useQueryClient();

  return (
    <div className="flex max-w-2xl flex-col gap-10">
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Shop address</h1>
        <p className="mt-1 text-sm text-neutral-500">
          Where your customers and kiosks reach your FitMirror shop.
        </p>
      </div>

      <SubdomainSection onSaved={() => queryClient.invalidateQueries({ queryKey: ['subdomain'] })} />

      {/*
        The overlay does its own plan check against the same `features` map
        GET /auth/me returns, and renders the children untouched when the
        plan includes the feature — so the real section is passed straight
        through rather than being conditionally mounted here.
      */}
      <FeatureLockedOverlay featureKey="custom_domain" label="A custom domain">
        <CustomDomainSection />
      </FeatureLockedOverlay>
    </div>
  );
}

function SubdomainSection({ onSaved }: { onSaved: () => void }) {
  const [value, setValue] = useState('');
  const [check, setCheck] = useState<SubdomainCheck | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const currentQuery = useQuery({ queryKey: ['subdomain'], queryFn: fetchSubdomain });

  useEffect(() => {
    if (currentQuery.data?.subdomain) setValue(currentQuery.data.subdomain);
  }, [currentQuery.data]);

  // Debounced so typing "gulshan-flagship" is one request at the end, not
  // sixteen — the endpoint carries the per-tenant rate limiter for exactly
  // this reason, and this keeps well inside it.
  useEffect(() => {
    const candidate = value.trim();

    if (candidate === '' || candidate === currentQuery.data?.subdomain) {
      setCheck(null);
      return;
    }

    const timer = window.setTimeout(() => {
      checkSubdomain(candidate)
        .then(setCheck)
        .catch(() => setCheck(null));
    }, CHECK_DEBOUNCE_MS);

    return () => window.clearTimeout(timer);
  }, [value, currentQuery.data?.subdomain]);

  const mutation = useMutation({
    mutationFn: () => assignSubdomain(value.trim()),
    onSuccess: () => {
      setError(null);
      setSaved(true);
      onSaved();
    },
    onError: (mutationError) => {
      setSaved(false);
      setError(
        mutationError instanceof ApiError ? mutationError.message : 'Unable to update your address.',
      );
    },
  });

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(false);
    mutation.mutate();
  };

  const isUnchanged = value.trim() === (currentQuery.data?.subdomain ?? '');

  return (
    <section className="flex flex-col gap-4">
      <div>
        <h2 className="text-base font-semibold text-neutral-900">FitMirror address</h2>
        <p className="mt-1 text-sm text-neutral-500">
          Your shop is always reachable here, even after you connect your own domain.
        </p>
      </div>

      <form className="flex flex-col gap-4" onSubmit={handleSubmit} noValidate>
        <Input
          label="Address"
          value={value}
          onChange={(event) => setValue(event.target.value.toLowerCase())}
          error={check && !check.available ? (check.reason ?? undefined) : undefined}
          hint={
            check?.available && check.url
              ? `Available — ${check.url}`
              : currentQuery.data?.url
                ? `Currently ${currentQuery.data.url}`
                : 'Lowercase letters, numbers and hyphens.'
          }
          disabled={mutation.isPending}
        />

        {error && (
          <p role="alert" className="text-danger-600 text-sm">
            {error}
          </p>
        )}
        {saved && <p className="text-success-700 text-sm">Address updated.</p>}

        <div className="flex justify-end">
          <Button
            type="submit"
            isLoading={mutation.isPending}
            disabled={isUnchanged || check?.available === false}
          >
            Save address
          </Button>
        </div>
      </form>
    </section>
  );
}

function CustomDomainSection() {
  const queryClient = useQueryClient();
  const [domain, setDomain] = useState('');
  const [error, setError] = useState<string | null>(null);

  const domainQuery = useQuery({ queryKey: ['custom-domain'], queryFn: fetchCustomDomain });
  const request = domainQuery.data?.request ?? null;

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['custom-domain'] });

  const requestMutation = useMutation({
    mutationFn: () => requestCustomDomain(domain.trim()),
    onSuccess: () => {
      setError(null);
      setDomain('');
      void invalidate();
    },
    onError: (mutationError) => {
      setError(
        mutationError instanceof ApiError ? mutationError.message : 'Unable to save that domain.',
      );
    },
  });

  const verifyMutation = useMutation({ mutationFn: verifyCustomDomain, onSuccess: invalidate });
  const removeMutation = useMutation({ mutationFn: removeCustomDomain, onSuccess: invalidate });

  return (
    <section className="flex flex-col gap-4">
      <div>
        <h2 className="text-base font-semibold text-neutral-900">Your own domain</h2>
        <p className="mt-1 text-sm text-neutral-500">
          Serve FitMirror from a domain you own, for example shop.yourbrand.com.
        </p>
      </div>

      {request ? (
        <div className="flex flex-col gap-4 rounded-lg border border-neutral-200 p-4">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="font-medium text-neutral-900">{request.domain}</p>
              <p
                className={
                  request.is_verified ? 'text-success-700 text-sm' : 'text-sm text-neutral-500'
                }
              >
                {request.status_label}
                {request.last_checked_at &&
                  ` — last checked ${new Date(request.last_checked_at).toLocaleString()}`}
              </p>
            </div>
            <Button
              variant="ghost"
              size="sm"
              isLoading={removeMutation.isPending}
              onClick={() => {
                if (window.confirm(`Disconnect ${request.domain}?`)) removeMutation.mutate();
              }}
            >
              Disconnect
            </Button>
          </div>

          {!request.is_verified && (
            <>
              <div>
                <p className="text-sm font-medium text-neutral-700">
                  Add this record in your DNS provider
                </p>
                <dl className="mt-2 grid grid-cols-[80px_1fr] gap-y-2 rounded-md bg-neutral-50 p-3 text-sm">
                  <dt className="text-neutral-500">Type</dt>
                  <dd className="font-mono text-neutral-800">{request.dns.type}</dd>
                  <dt className="text-neutral-500">Name</dt>
                  <dd className="break-all font-mono text-neutral-800">{request.dns.name}</dd>
                  <dt className="text-neutral-500">Value</dt>
                  <dd className="break-all font-mono text-neutral-800">{request.dns.value}</dd>
                  <dt className="text-neutral-500">TTL</dt>
                  <dd className="font-mono text-neutral-800">{request.dns.ttl}</dd>
                </dl>
                <p className="mt-2 text-xs text-neutral-500">
                  Some providers append your domain automatically — if so, enter only{' '}
                  <span className="font-mono">
                    {request.dns.name.replace(`.${request.domain}`, '')}
                  </span>{' '}
                  as the name.
                </p>
              </div>

              {request.last_error && (
                <p className="text-sm text-neutral-600">{request.last_error}</p>
              )}

              <div className="flex justify-end">
                <Button
                  size="sm"
                  isLoading={verifyMutation.isPending}
                  onClick={() => verifyMutation.mutate()}
                >
                  Check DNS now
                </Button>
              </div>
            </>
          )}
        </div>
      ) : (
        <form
          className="flex flex-col gap-4"
          onSubmit={(event) => {
            event.preventDefault();
            setError(null);
            requestMutation.mutate();
          }}
          noValidate
        >
          <Input
            label="Domain"
            placeholder="shop.yourbrand.com"
            value={domain}
            onChange={(event) => setDomain(event.target.value)}
            hint="Enter the hostname, or paste the full URL — either works."
            disabled={requestMutation.isPending}
          />

          {error && (
            <p role="alert" className="text-danger-600 text-sm">
              {error}
            </p>
          )}

          <div className="flex justify-end">
            <Button
              type="submit"
              isLoading={requestMutation.isPending}
              disabled={domain.trim() === ''}
            >
              Connect domain
            </Button>
          </div>
        </form>
      )}
    </section>
  );
}
