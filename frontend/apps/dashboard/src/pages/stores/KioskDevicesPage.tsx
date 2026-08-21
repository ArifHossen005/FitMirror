import { Button, DataTable, type DataTableColumn } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';

import { KioskPairingModal } from '../../components/stores/KioskPairingModal';
import { KioskSettingsForm } from '../../components/stores/KioskSettingsForm';
import { Can } from '../../hooks/usePermissions';
import {
  deleteKioskDevice,
  fetchKioskDevices,
  fetchStore,
  type KioskDevice,
  type KioskDeviceWithCode,
  reactivateKioskDevice,
  regenerateKioskPairingCode,
  suspendKioskDevice,
  toPaginationMeta,
  unpairKioskDevice,
} from '../../lib/stores';

function relativeLastSeen(value: string | null): string {
  if (!value) return 'Never';

  const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000);

  if (seconds < 90) return 'Just now';
  if (seconds < 3600) return `${Math.round(seconds / 60)} min ago`;
  if (seconds < 86400) return `${Math.round(seconds / 3600)} h ago`;

  return new Date(value).toLocaleDateString();
}

/**
 * Kiosk devices for one branch: pair a new one, see whether it is online,
 * change its display settings, suspend, unpair or remove it.
 *
 * Devices report in on a 60-second heartbeat, so this list refetches on the
 * same cadence — without it a staff member watching the screen during setup
 * would see "Never seen" for a minute after the kiosk had already connected.
 */
export function KioskDevicesPage() {
  const params = useParams<{ storeId: string }>();
  const storeId = Number(params.storeId);
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [pairing, setPairing] = useState<KioskDeviceWithCode | null>(null);
  const [isRegistering, setIsRegistering] = useState(false);
  const [settingsFor, setSettingsFor] = useState<KioskDevice | null>(null);

  const storeQuery = useQuery({
    queryKey: ['stores', 'detail', storeId],
    queryFn: () => fetchStore(storeId),
  });

  const devicesQuery = useQuery({
    queryKey: ['stores', storeId, 'kiosk-devices', page],
    queryFn: () => fetchKioskDevices(storeId, page),
    refetchInterval: 60_000,
  });

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ['stores', storeId, 'kiosk-devices'] });

  const regenerateMutation = useMutation({
    mutationFn: regenerateKioskPairingCode,
    onSuccess: (device) => {
      setPairing(device);
      void invalidate();
    },
  });

  const unpairMutation = useMutation({
    mutationFn: unpairKioskDevice,
    onSuccess: (device) => {
      setPairing(device);
      void invalidate();
    },
  });

  const suspendMutation = useMutation({ mutationFn: suspendKioskDevice, onSuccess: invalidate });
  const reactivateMutation = useMutation({ mutationFn: reactivateKioskDevice, onSuccess: invalidate });
  const deleteMutation = useMutation({ mutationFn: deleteKioskDevice, onSuccess: invalidate });

  const columns: DataTableColumn<KioskDevice>[] = [
    {
      key: 'name',
      header: 'Device',
      render: (row) => (
        <div>
          <p className="font-medium text-neutral-900">{row.name}</p>
          <p className="text-xs text-neutral-400">
            {row.app_version ? `App ${row.app_version}` : 'Version unknown'}
          </p>
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) => (
        <div className="flex flex-col gap-1">
          <span
            className={
              row.status === 'paired'
                ? 'text-success-700 bg-success-50 w-fit rounded-full px-2 py-0.5 text-xs font-medium'
                : row.status === 'suspended'
                  ? 'bg-danger-50 text-danger-700 w-fit rounded-full px-2 py-0.5 text-xs font-medium'
                  : 'w-fit rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600'
            }
          >
            {row.status_label}
          </span>
          {row.has_pending_code && (
            <span className="text-xs text-neutral-400">Pairing code waiting</span>
          )}
        </div>
      ),
    },
    {
      key: 'last_seen',
      header: 'Last seen',
      render: (row) => (
        <div className="flex items-center gap-2 text-sm text-neutral-600">
          <span
            aria-hidden
            className={`h-2 w-2 rounded-full ${row.is_online ? 'bg-success-500' : 'bg-neutral-300'}`}
          />
          {relativeLastSeen(row.last_seen_at)}
        </div>
      ),
    },
    {
      key: 'health',
      header: 'Health',
      render: (row) => {
        if (!row.health) return <span className="text-xs text-neutral-400">No report</span>;

        const camera = row.health['camera_ok'];
        const battery = row.health['battery_percent'];

        return (
          <div className="text-xs text-neutral-600">
            {camera === false && <p className="text-danger-600">Camera unavailable</p>}
            {camera === true && <p>Camera OK</p>}
            {typeof battery === 'number' && <p>Battery {battery}%</p>}
          </div>
        );
      },
    },
    {
      key: 'actions',
      header: '',
      className: 'text-right',
      render: (row) => (
        <div className="flex flex-wrap justify-end gap-2">
          <Can permission="kiosk.manage">
            <Button variant="ghost" size="sm" onClick={() => setSettingsFor(row)}>
              Settings
            </Button>
          </Can>
          <Can permission="kiosk.manage">
            {row.status === 'paired' ? (
              <Button
                variant="outline"
                size="sm"
                isLoading={unpairMutation.isPending && unpairMutation.variables === row.id}
                onClick={() => {
                  if (window.confirm(`Unpair ${row.name}? It will stop working until re-paired.`)) {
                    unpairMutation.mutate(row.id);
                  }
                }}
              >
                Unpair
              </Button>
            ) : (
              <Button
                variant="outline"
                size="sm"
                isLoading={regenerateMutation.isPending && regenerateMutation.variables === row.id}
                onClick={() => regenerateMutation.mutate(row.id)}
              >
                Show pairing code
              </Button>
            )}
          </Can>
          <Can permission="kiosk.manage">
            {row.status === 'suspended' ? (
              <Button
                variant="ghost"
                size="sm"
                isLoading={reactivateMutation.isPending && reactivateMutation.variables === row.id}
                onClick={() => reactivateMutation.mutate(row.id)}
              >
                Reactivate
              </Button>
            ) : (
              <Button
                variant="ghost"
                size="sm"
                isLoading={suspendMutation.isPending && suspendMutation.variables === row.id}
                onClick={() => suspendMutation.mutate(row.id)}
              >
                Suspend
              </Button>
            )}
          </Can>
          <Can permission="kiosk.manage">
            <Button
              variant="danger"
              size="sm"
              isLoading={deleteMutation.isPending && deleteMutation.variables === row.id}
              onClick={() => {
                if (window.confirm(`Remove ${row.name} permanently?`)) deleteMutation.mutate(row.id);
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
          <button
            type="button"
            onClick={() => navigate('/stores')}
            className="text-sm text-neutral-500 hover:text-neutral-700"
          >
            ← Back to branches
          </button>
          <h1 className="mt-2 text-lg font-semibold text-neutral-900">
            Kiosks {storeQuery.data ? `— ${storeQuery.data.name}` : ''}
          </h1>
          <p className="mt-1 text-sm text-neutral-500">
            Tablets and laptops running the try-on experience in this branch.
          </p>
        </div>
        <Can permission="kiosk.manage">
          <Button onClick={() => setIsRegistering(true)}>Pair a device</Button>
        </Can>
      </div>

      <DataTable
        columns={columns}
        data={devicesQuery.data?.data ?? []}
        getRowId={(row) => row.id}
        isLoading={devicesQuery.isLoading}
        emptyMessage="No kiosks paired to this branch yet."
        pagination={toPaginationMeta(devicesQuery.data)}
        onPageChange={setPage}
      />

      <KioskPairingModal
        storeId={storeId}
        isOpen={isRegistering || pairing !== null}
        existingDevice={pairing}
        onClose={() => {
          setIsRegistering(false);
          setPairing(null);
        }}
        onPaired={() => void invalidate()}
      />

      {settingsFor && (
        <KioskSettingsForm
          device={settingsFor}
          onClose={() => setSettingsFor(null)}
          onSaved={() => void invalidate()}
        />
      )}
    </div>
  );
}
