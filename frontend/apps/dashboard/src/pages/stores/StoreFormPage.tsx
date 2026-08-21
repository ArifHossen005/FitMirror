import { Button, Input, Select } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';

import { PlanLimitModal } from '../../components/billing/PlanLimitModal';
import { StoreMapPicker } from '../../components/stores/StoreMapPicker';
import { parsePlanLimitError,type PlanLimitErrorDetails } from '../../lib/billing';
import {
  ApiError,
  createStore,
  fetchStore,
  type SocialNetwork,
  STORE_STATUSES,
  type StorePayload,
  SUPPORTED_SOCIALS,
  updateStore,
} from '../../lib/stores';
import { StoreBrandingPanel } from './StoreBrandingPanel';

const SOCIAL_LABELS: Record<SocialNetwork, string> = {
  facebook: 'Facebook',
  instagram: 'Instagram',
  tiktok: 'TikTok',
  youtube: 'YouTube',
  whatsapp: 'WhatsApp',
  website: 'Website',
};

interface FormState {
  name: string;
  code: string;
  phone: string;
  email: string;
  address: string;
  city: string;
  area: string;
  lat: string;
  lng: string;
  map_url: string;
  status: string;
  is_main: boolean;
  socials: Partial<Record<SocialNetwork, string>>;
}

const EMPTY_FORM: FormState = {
  name: '',
  code: '',
  phone: '',
  email: '',
  address: '',
  city: '',
  area: '',
  lat: '',
  lng: '',
  map_url: '',
  status: 'active',
  is_main: false,
  socials: {},
};

/**
 * Create and edit share one component because the two forms are the same
 * fields — only the submit target differs. Branding (logo/banner) is a
 * separate panel rather than a field here: it posts multipart to its own
 * endpoint and only exists once the branch has an id to upload against.
 */
export function StoreFormPage() {
  const params = useParams<{ storeId: string }>();
  const storeId = params.storeId ? Number(params.storeId) : null;
  const isEditing = storeId !== null;

  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [limitDetails, setLimitDetails] = useState<PlanLimitErrorDetails | null>(null);

  const storeQuery = useQuery({
    queryKey: ['stores', 'detail', storeId],
    queryFn: () => fetchStore(storeId as number),
    enabled: isEditing,
  });

  useEffect(() => {
    const store = storeQuery.data;
    if (!store) return;

    setForm({
      name: store.name,
      code: store.code,
      phone: store.phone ?? '',
      email: store.email ?? '',
      address: store.address ?? '',
      city: store.city ?? '',
      area: store.area ?? '',
      lat: store.lat === null ? '' : String(store.lat),
      lng: store.lng === null ? '' : String(store.lng),
      map_url: store.map_url ?? '',
      status: store.status,
      is_main: store.is_main,
      socials: store.socials ?? {},
    });
  }, [storeQuery.data]);

  const mutation = useMutation({
    mutationFn: (payload: StorePayload) =>
      isEditing ? updateStore(storeId, payload) : createStore(payload),
    onSuccess: (store) => {
      void queryClient.invalidateQueries({ queryKey: ['stores'] });
      navigate(`/stores/${store.id}`, { replace: true });
    },
    onError: (mutationError) => {
      const planLimit = parsePlanLimitError(mutationError);

      if (planLimit) {
        setLimitDetails(planLimit);
        return;
      }

      if (mutationError instanceof ApiError) {
        setError(mutationError.message);
        setFieldErrors(mutationError.fieldErrors);
        return;
      }

      setError('Unable to save this branch.');
    },
  });

  const update = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((current) => ({ ...current, [key]: value }));

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    setFieldErrors({});

    // Blank optional text fields are sent as null, not '' — the API
    // treats null as "no value" and would happily persist an empty string
    // as a real phone number the kiosk would then try to display.
    const orNull = (value: string) => (value.trim() === '' ? null : value.trim());

    const payload: StorePayload = {
      name: form.name.trim(),
      code: form.code.trim(),
      phone: orNull(form.phone),
      email: orNull(form.email),
      address: orNull(form.address),
      city: orNull(form.city),
      area: orNull(form.area),
      lat: form.lat.trim() === '' ? null : Number(form.lat),
      lng: form.lng.trim() === '' ? null : Number(form.lng),
      map_url: orNull(form.map_url),
      status: form.status,
      socials: form.socials,
    };

    // is_main is only ever sent as `true` — the API rejects unsetting it
    // directly, since a tenant must always have exactly one main branch.
    if (form.is_main) payload.is_main = true;

    mutation.mutate(payload);
  };

  const fieldError = (field: string) => fieldErrors[field]?.[0];

  if (isEditing && storeQuery.isLoading) {
    return <p className="text-sm text-neutral-500">Loading branch…</p>;
  }

  return (
    <div className="flex flex-col gap-8">
      <div>
        <button
          type="button"
          onClick={() => navigate('/stores')}
          className="text-sm text-neutral-500 hover:text-neutral-700"
        >
          ← Back to branches
        </button>
        <h1 className="mt-2 text-lg font-semibold text-neutral-900">
          {isEditing ? form.name || 'Edit branch' : 'Add a branch'}
        </h1>
        <p className="mt-1 text-sm text-neutral-500">
          Contact details, address and social links shown on the kiosk and in campaigns.
        </p>
      </div>

      <form className="flex max-w-3xl flex-col gap-6" onSubmit={handleSubmit} noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label="Branch name"
            required
            value={form.name}
            onChange={(event) => update('name', event.target.value)}
            error={fieldError('name')}
            disabled={mutation.isPending}
          />
          <Input
            label="Branch code"
            required
            value={form.code}
            onChange={(event) => update('code', event.target.value)}
            error={fieldError('code')}
            hint="Short identifier shown on receipts and kiosks, e.g. DHK-01."
            disabled={mutation.isPending}
          />
          <Input
            label="Phone"
            value={form.phone}
            onChange={(event) => update('phone', event.target.value)}
            error={fieldError('phone')}
            disabled={mutation.isPending}
          />
          <Input
            label="Email"
            type="email"
            value={form.email}
            onChange={(event) => update('email', event.target.value)}
            error={fieldError('email')}
            disabled={mutation.isPending}
          />
        </div>

        <Input
          label="Street address"
          value={form.address}
          onChange={(event) => update('address', event.target.value)}
          error={fieldError('address')}
          disabled={mutation.isPending}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label="City"
            value={form.city}
            onChange={(event) => update('city', event.target.value)}
            error={fieldError('city')}
            disabled={mutation.isPending}
          />
          <Input
            label="Area"
            value={form.area}
            onChange={(event) => update('area', event.target.value)}
            error={fieldError('area')}
            disabled={mutation.isPending}
          />
        </div>

        <StoreMapPicker
          lat={form.lat}
          lng={form.lng}
          mapUrl={form.map_url}
          disabled={mutation.isPending}
          latError={fieldError('lat')}
          lngError={fieldError('lng')}
          mapUrlError={fieldError('map_url')}
          onChange={(next) => {
            update('lat', next.lat);
            update('lng', next.lng);
            update('map_url', next.mapUrl);
          }}
        />

        <fieldset className="flex flex-col gap-4">
          <legend className="text-sm font-medium text-neutral-700">Social links</legend>
          <div className="grid gap-4 sm:grid-cols-2">
            {SUPPORTED_SOCIALS.map((network) => (
              <Input
                key={network}
                label={SOCIAL_LABELS[network]}
                type="url"
                placeholder="https://"
                value={form.socials[network] ?? ''}
                onChange={(event) => {
                  const value = event.target.value;
                  setForm((current) => {
                    const socials = { ...current.socials };
                    if (value.trim() === '') delete socials[network];
                    else socials[network] = value;
                    return { ...current, socials };
                  });
                }}
                error={fieldError(`socials.${network}`)}
                disabled={mutation.isPending}
              />
            ))}
          </div>
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2">
          <Select
            label="Status"
            value={form.status}
            onChange={(event) => update('status', event.target.value)}
            options={STORE_STATUSES.map((status) => ({ value: status.value, label: status.label }))}
            error={fieldError('status')}
            hint="A permanently closed branch keeps its history but stops using a plan slot."
            disabled={mutation.isPending}
          />
          {!form.is_main && (
            <label className="flex items-end gap-2 pb-2 text-sm text-neutral-700">
              <input
                type="checkbox"
                checked={form.is_main}
                onChange={(event) => update('is_main', event.target.checked)}
                disabled={mutation.isPending}
              />
              Make this the main branch
            </label>
          )}
        </div>

        {error && (
          <p role="alert" className="text-danger-600 text-sm">
            {error}
          </p>
        )}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => navigate('/stores')}>
            Cancel
          </Button>
          <Button type="submit" isLoading={mutation.isPending}>
            {isEditing ? 'Save changes' : 'Create branch'}
          </Button>
        </div>
      </form>

      {isEditing && storeQuery.data && <StoreBrandingPanel store={storeQuery.data} />}

      <PlanLimitModal
        isOpen={limitDetails !== null}
        onClose={() => setLimitDetails(null)}
        details={limitDetails}
      />
    </div>
  );
}
