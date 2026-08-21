import { Button, Modal } from '@fitmirror/ui';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type ChangeEvent, useRef, useState } from 'react';

import { ImageCropper } from '../../components/stores/ImageCropper';
import { Can } from '../../hooks/usePermissions';
import { ApiError, type Store, updateStoreBranding } from '../../lib/stores';

type BrandingField = 'logo' | 'banner';

const FIELD_CONFIG: Record<
  BrandingField,
  { label: string; aspectRatio: number; outputWidth: number; hint: string }
> = {
  logo: {
    label: 'Logo',
    aspectRatio: 1,
    outputWidth: 512,
    hint: 'Square. Shown on the kiosk header and on invoices.',
  },
  banner: {
    label: 'Banner',
    aspectRatio: 3,
    outputWidth: 1536,
    hint: 'Wide. Used as the kiosk attract screen background.',
  },
};

/** Matches StoreBrandingRequest::MAX_KILOBYTES on the backend. */
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

/**
 * Logo and banner upload with crop. Lives inside the branch edit page
 * rather than as its own route because it needs a saved branch to upload
 * against — there is no id to POST to until the branch exists.
 */
export function StoreBrandingPanel({ store }: { store: Store }) {
  const queryClient = useQueryClient();
  const [cropping, setCropping] = useState<{ field: BrandingField; file: File } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const inputRefs = {
    logo: useRef<HTMLInputElement>(null),
    banner: useRef<HTMLInputElement>(null),
  };

  const mutation = useMutation({
    mutationFn: (payload: Parameters<typeof updateStoreBranding>[1]) =>
      updateStoreBranding(store.id, payload),
    onSuccess: () => {
      setError(null);
      void queryClient.invalidateQueries({ queryKey: ['stores'] });
    },
    onError: (mutationError) => {
      setError(
        mutationError instanceof ApiError ? mutationError.message : 'Unable to update branding.',
      );
    },
  });

  const handleFilePicked = (field: BrandingField) => (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    // The input is reset immediately so picking the *same* file twice in a
    // row still fires a change event.
    event.target.value = '';

    if (!file) return;

    if (file.size > MAX_UPLOAD_BYTES) {
      setError('That image is larger than 5 MB. Choose a smaller file.');
      return;
    }

    setError(null);
    setCropping({ field, file });
  };

  const currentUrl = (field: BrandingField) =>
    field === 'logo' ? store.logo_url : store.banner_url;

  return (
    <section className="flex max-w-3xl flex-col gap-6 border-t border-neutral-200 pt-8">
      <div>
        <h2 className="text-base font-semibold text-neutral-900">Branding</h2>
        <p className="mt-1 text-sm text-neutral-500">
          Images shown on this branch's kiosk screens.
        </p>
      </div>

      {error && (
        <p role="alert" className="text-danger-600 text-sm">
          {error}
        </p>
      )}

      <div className="grid gap-6 sm:grid-cols-2">
        {(Object.keys(FIELD_CONFIG) as BrandingField[]).map((field) => {
          const config = FIELD_CONFIG[field];
          const url = currentUrl(field);

          return (
            <div key={field} className="flex flex-col gap-3">
              <div className="flex items-baseline justify-between">
                <span className="text-sm font-medium text-neutral-700">{config.label}</span>
                {url && (
                  <Can permission="stores.update">
                    <button
                      type="button"
                      className="text-danger-600 text-xs hover:underline"
                      onClick={() =>
                        mutation.mutate(
                          field === 'logo' ? { removeLogo: true } : { removeBanner: true },
                        )
                      }
                    >
                      Remove
                    </button>
                  </Can>
                )}
              </div>

              <div
                className="flex items-center justify-center overflow-hidden rounded-lg border border-dashed border-neutral-300 bg-neutral-50"
                style={{ aspectRatio: String(config.aspectRatio) }}
              >
                {url ? (
                  <img src={url} alt={`${store.name} ${config.label}`} className="h-full w-full object-cover" />
                ) : (
                  <span className="text-xs text-neutral-400">No {config.label.toLowerCase()} yet</span>
                )}
              </div>

              <p className="text-xs text-neutral-500">{config.hint}</p>

              <Can permission="stores.update">
                <>
                  <input
                    ref={inputRefs[field]}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="hidden"
                    onChange={handleFilePicked(field)}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => inputRefs[field].current?.click()}
                    disabled={mutation.isPending}
                  >
                    {url ? `Replace ${config.label.toLowerCase()}` : `Upload ${config.label.toLowerCase()}`}
                  </Button>
                </>
              </Can>
            </div>
          );
        })}
      </div>

      <Modal
        isOpen={cropping !== null}
        onClose={() => setCropping(null)}
        title={cropping ? `Crop ${FIELD_CONFIG[cropping.field].label.toLowerCase()}` : ''}
        size="lg"
      >
        {cropping && (
          <ImageCropper
            file={cropping.file}
            aspectRatio={FIELD_CONFIG[cropping.field].aspectRatio}
            outputWidth={FIELD_CONFIG[cropping.field].outputWidth}
            onCancel={() => setCropping(null)}
            onCropped={(blob) => {
              const field = cropping.field;
              setCropping(null);
              mutation.mutate(field === 'logo' ? { logo: blob } : { banner: blob });
            }}
          />
        )}
      </Modal>
    </section>
  );
}
