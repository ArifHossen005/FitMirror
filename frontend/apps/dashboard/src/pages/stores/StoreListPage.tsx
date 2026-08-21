import { Button, DataTable, type DataTableColumn } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

import { PlanLimitModal } from '../../components/billing/PlanLimitModal';
import { Can } from '../../hooks/usePermissions';
import { deleteStore, fetchStores, type Store, toPaginationMeta } from '../../lib/stores';

/**
 * Branch list with the tenant's plan headroom shown inline. The list
 * endpoint returns `branch_limit` alongside the rows precisely so "Add
 * branch" can be disabled without a second request — see StoreController's
 * own docblock.
 */
export function StoreListPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [limitModalOpen, setLimitModalOpen] = useState(false);

  const storesQuery = useQuery({
    queryKey: ['stores', page],
    queryFn: () => fetchStores(page),
  });

  const deleteMutation = useMutation({
    mutationFn: deleteStore,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['stores'] }),
  });

  const branchLimit = storesQuery.data?.branch_limit;
  const canCreateMore = branchLimit?.can_create_more ?? true;

  const columns: DataTableColumn<Store>[] = [
    {
      key: 'name',
      header: 'Branch',
      render: (row) => (
        <div className="flex items-center gap-3">
          {row.logo_url ? (
            <img src={row.logo_url} alt="" className="h-8 w-8 rounded object-cover" />
          ) : (
            <div className="flex h-8 w-8 items-center justify-center rounded bg-neutral-100 text-xs font-semibold text-neutral-500">
              {row.code.slice(0, 2)}
            </div>
          )}
          <div>
            <p className="font-medium text-neutral-900">
              {row.name}
              {row.is_main && (
                <span className="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">
                  Main
                </span>
              )}
            </p>
            <p className="text-xs text-neutral-400">{row.code}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'location',
      header: 'Location',
      render: (row) => (
        <div className="text-sm text-neutral-600">
          <p>{[row.area, row.city].filter(Boolean).join(', ') || '—'}</p>
          {row.phone && <p className="text-xs text-neutral-400">{row.phone}</p>}
        </div>
      ),
    },
    {
      key: 'kiosks',
      header: 'Kiosks',
      render: (row) => (
        <Link to={`/stores/${row.id}/kiosks`} className="text-brand-600 text-sm hover:underline">
          {row.kiosk_device_count} {row.kiosk_device_count === 1 ? 'device' : 'devices'}
        </Link>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) => (
        <span
          className={
            row.status === 'active'
              ? 'text-success-700 bg-success-50 rounded-full px-2 py-0.5 text-xs font-medium'
              : 'rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600'
          }
        >
          {row.status_label}
        </span>
      ),
    },
    {
      key: 'actions',
      header: '',
      className: 'text-right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          <Can permission="stores.update">
            <Button variant="outline" size="sm" onClick={() => navigate(`/stores/${row.id}`)}>
              Edit
            </Button>
          </Can>
          <Can permission="stores.update">
            <Button variant="ghost" size="sm" onClick={() => navigate(`/stores/${row.id}/hours`)}>
              Hours
            </Button>
          </Can>
          <Can permission="stores.delete">
            <Button
              variant="danger"
              size="sm"
              isLoading={deleteMutation.isPending && deleteMutation.variables === row.id}
              onClick={() => {
                if (window.confirm(`Remove ${row.name}? Its kiosks will be unpaired.`)) {
                  deleteMutation.mutate(row.id);
                }
              }}
            >
              Remove
            </Button>
          </Can>
        </div>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-8">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-lg font-semibold text-neutral-900">Branches</h1>
          <p className="mt-1 text-sm text-neutral-500">
            Every shop location you run FitMirror in.
            {branchLimit && (
              <span className="ml-1">
                Using <strong>{branchLimit.used}</strong> of{' '}
                <strong>{branchLimit.max ?? 'unlimited'}</strong>.
              </span>
            )}
          </p>
        </div>
        <Can permission="stores.create">
          <Button
            onClick={() => (canCreateMore ? navigate('/stores/new') : setLimitModalOpen(true))}
          >
            Add branch
          </Button>
        </Can>
      </div>

      {deleteMutation.isError && (
        <p role="alert" className="text-danger-600 text-sm">
          {deleteMutation.error instanceof Error
            ? deleteMutation.error.message
            : 'Unable to remove that branch.'}
        </p>
      )}

      <DataTable
        columns={columns}
        data={storesQuery.data?.stores ?? []}
        getRowId={(row) => row.id}
        isLoading={storesQuery.isLoading}
        emptyMessage="No branches yet. Add your first shop location to get started."
        pagination={toPaginationMeta(
          storesQuery.data ? { data: storesQuery.data.stores, meta: storesQuery.data.meta } : undefined,
        )}
        onPageChange={setPage}
      />

      {/*
        Built from the list endpoint's own branch_limit rather than from a
        caught 403: the button is disabled before the request is ever made,
        so there is no plan_limit_exceeded error to parse. The shape is the
        same one PlanGateResponse::limitExceeded() produces, so the modal
        renders identically either way.
      */}
      <PlanLimitModal
        isOpen={limitModalOpen}
        onClose={() => setLimitModalOpen(false)}
        details={
          branchLimit && branchLimit.max !== null
            ? {
                limit: 'branches',
                current: branchLimit.used,
                max: branchLimit.max,
                upgradeUrl: '/billing',
              }
            : null
        }
      />
    </div>
  );
}
