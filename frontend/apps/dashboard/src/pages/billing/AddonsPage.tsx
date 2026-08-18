import { Button, Select } from '@fitmirror/ui';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { type AddonSummary, ApiError, fetchAddons, purchaseAddon } from '../../lib/billing';

const QUANTITY_OPTIONS = [1, 2, 3, 5, 10].map((n) => ({ value: String(n), label: String(n) }));

/** The add-on marketplace — SMS/storage/support/template packs, checkout reuses the same SSLCommerz redirect flow as a plan purchase. */
export function AddonsPage() {
  const addonsQuery = useQuery({ queryKey: ['billing', 'addons'], queryFn: fetchAddons });
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [error, setError] = useState<string | null>(null);

  const purchaseMutation = useMutation({
    mutationFn: ({ addonId, quantity }: { addonId: number; quantity: number }) => purchaseAddon(addonId, quantity),
    onSuccess: (result) => {
      window.location.href = result.gateway_url;
    },
    onError: (mutationError) => {
      setError(mutationError instanceof ApiError ? mutationError.message : 'Unable to start checkout.');
    },
  });

  return (
    <div className="flex flex-col gap-6 p-6">
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Add-on Marketplace</h1>
        <p className="mt-1 text-sm text-neutral-500">Top up SMS, storage, support, and campaign templates.</p>
      </div>

      {error && (
        <p role="alert" className="text-danger-600 text-sm">
          {error}
        </p>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {addonsQuery.data?.map((addon: AddonSummary) => {
          const quantity = quantities[addon.id] ?? 1;

          return (
            <div key={addon.id} className="flex flex-col justify-between rounded-lg border border-neutral-200 bg-white p-5">
              <div>
                <h2 className="text-sm font-semibold text-neutral-900">{addon.name}</h2>
                {addon.description && <p className="mt-1 text-xs text-neutral-500">{addon.description}</p>}
                <p className="mt-3 text-lg font-bold text-neutral-900">
                  ৳{addon.price}
                  <span className="text-xs font-normal text-neutral-400"> / pack</span>
                </p>
              </div>

              <div className="mt-4 flex items-center gap-2">
                <Select
                  className="w-20"
                  value={String(quantity)}
                  onChange={(event) =>
                    setQuantities((prev) => ({ ...prev, [addon.id]: Number(event.target.value) }))
                  }
                  options={QUANTITY_OPTIONS}
                />
                <Button
                  className="flex-1"
                  size="sm"
                  isLoading={purchaseMutation.isPending && purchaseMutation.variables?.addonId === addon.id}
                  onClick={() => {
                    setError(null);
                    purchaseMutation.mutate({ addonId: addon.id, quantity });
                  }}
                >
                  Buy
                </Button>
              </div>
            </div>
          );
        })}
      </div>

      {addonsQuery.data?.length === 0 && (
        <p className="text-sm text-neutral-500">No add-ons are available right now.</p>
      )}
    </div>
  );
}
