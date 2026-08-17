import { Button, DataTable, type DataTableColumn, Modal, Select } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { Can } from '../../hooks/usePermissions';
import { ApiError } from '../../lib/auth';
import {
  deactivateStaff,
  deleteStaff,
  fetchPendingInvitations,
  fetchStaff,
  INVITABLE_ROLES,
  inviteStaff,
  reactivateStaff,
  revokeInvitation,
  type StaffInvitation,
  type StaffMember,
  toPaginationMeta,
  updateStaffRole,
} from '../../lib/staff';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

export function StaffListPage() {
  const currentUserId = useDashboardAuthStore((state) => state.user?.id);
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [invitationsPage, setInvitationsPage] = useState(1);
  const [isInviteOpen, setIsInviteOpen] = useState(false);

  const staffQuery = useQuery({
    queryKey: ['staff', page],
    queryFn: () => fetchStaff(page),
  });

  const invitationsQuery = useQuery({
    queryKey: ['staff', 'invitations', invitationsPage],
    queryFn: () => fetchPendingInvitations(invitationsPage),
  });

  const invalidateStaff = () => queryClient.invalidateQueries({ queryKey: ['staff'] });

  const roleMutation = useMutation({
    mutationFn: ({ userId, role }: { userId: number; role: string }) => updateStaffRole(userId, role),
    onSuccess: invalidateStaff,
  });

  const deactivateMutation = useMutation({ mutationFn: deactivateStaff, onSuccess: invalidateStaff });
  const reactivateMutation = useMutation({ mutationFn: reactivateStaff, onSuccess: invalidateStaff });
  const deleteMutation = useMutation({ mutationFn: deleteStaff, onSuccess: invalidateStaff });
  const revokeMutation = useMutation({ mutationFn: revokeInvitation, onSuccess: invalidateStaff });

  const columns: DataTableColumn<StaffMember>[] = [
    { key: 'name', header: 'Name', render: (row) => (
      <div>
        <p className="font-medium text-neutral-900">{row.name}</p>
        <p className="text-xs text-neutral-400">{row.email}</p>
      </div>
    ) },
    {
      key: 'role',
      header: 'Role',
      render: (row) =>
        row.is_owner ? (
          <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">
            Owner
          </span>
        ) : (
          <Can permission="staff.update">
            <Select
              value={row.roles[0] ?? ''}
              onChange={(event) => roleMutation.mutate({ userId: row.id, role: event.target.value })}
              options={INVITABLE_ROLES.map((role) => ({ value: role, label: role }))}
              disabled={roleMutation.isPending}
              className="h-8 text-xs"
            />
          </Can>
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
              : 'bg-danger-50 text-danger-700 rounded-full px-2 py-0.5 text-xs font-medium'
          }
        >
          {row.status}
        </span>
      ),
    },
    {
      key: 'actions',
      header: '',
      render: (row) =>
        row.is_owner || row.id === currentUserId ? null : (
          <div className="flex justify-end gap-2">
            <Can permission="staff.deactivate">
              {row.status === 'active' ? (
                <Button
                  variant="outline"
                  size="sm"
                  isLoading={deactivateMutation.isPending && deactivateMutation.variables === row.id}
                  onClick={() => deactivateMutation.mutate(row.id)}
                >
                  Deactivate
                </Button>
              ) : (
                <Button
                  variant="outline"
                  size="sm"
                  isLoading={reactivateMutation.isPending && reactivateMutation.variables === row.id}
                  onClick={() => reactivateMutation.mutate(row.id)}
                >
                  Reactivate
                </Button>
              )}
            </Can>
            <Can permission="staff.delete">
              <Button
                variant="danger"
                size="sm"
                isLoading={deleteMutation.isPending && deleteMutation.variables === row.id}
                onClick={() => {
                  if (window.confirm(`Remove ${row.name} from your team? This cannot be undone.`)) {
                    deleteMutation.mutate(row.id);
                  }
                }}
              >
                Remove
              </Button>
            </Can>
          </div>
        ),
      className: 'text-right',
    },
  ];

  const invitationColumns: DataTableColumn<StaffInvitation>[] = [
    { key: 'email', header: 'Email' },
    { key: 'role', header: 'Role' },
    { key: 'invited_by', header: 'Invited by' },
    {
      key: 'expires_at',
      header: 'Expires',
      accessor: (row) => new Date(row.expires_at).toLocaleDateString(),
    },
    {
      key: 'actions',
      header: '',
      className: 'text-right',
      render: (row) => (
        <Can permission="staff.invite">
          <Button
            variant="ghost"
            size="sm"
            isLoading={revokeMutation.isPending && revokeMutation.variables === row.id}
            onClick={() => revokeMutation.mutate(row.id)}
          >
            Revoke
          </Button>
        </Can>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-8">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold text-neutral-900">Team</h1>
          <p className="mt-1 text-sm text-neutral-500">Manage who has access to your FitMirror dashboard.</p>
        </div>
        <Can permission="staff.invite">
          <Button onClick={() => setIsInviteOpen(true)}>Invite staff</Button>
        </Can>
      </div>

      <DataTable
        columns={columns}
        data={staffQuery.data?.data ?? []}
        getRowId={(row) => row.id}
        isLoading={staffQuery.isLoading}
        emptyMessage="No staff members yet."
        pagination={toPaginationMeta(staffQuery.data)}
        onPageChange={setPage}
      />

      {(invitationsQuery.data?.data.length ?? 0) > 0 && (
        <div>
          <h2 className="text-base font-semibold text-neutral-900">Pending invitations</h2>
          <div className="mt-2">
            <DataTable
              columns={invitationColumns}
              data={invitationsQuery.data?.data ?? []}
              getRowId={(row) => row.id}
              isLoading={invitationsQuery.isLoading}
              pagination={toPaginationMeta(invitationsQuery.data)}
              onPageChange={setInvitationsPage}
            />
          </div>
        </div>
      )}

      <InviteStaffModal
        isOpen={isInviteOpen}
        onClose={() => setIsInviteOpen(false)}
        onInvited={() => {
          setIsInviteOpen(false);
          void queryClient.invalidateQueries({ queryKey: ['staff'] });
        }}
      />
    </div>
  );
}

function InviteStaffModal({
  isOpen,
  onClose,
  onInvited,
}: {
  isOpen: boolean;
  onClose: () => void;
  onInvited: () => void;
}) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<string>('staff');
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => inviteStaff({ name: name.trim() || undefined, email: email.trim(), role }),
    onSuccess: () => {
      setName('');
      setEmail('');
      setRole('staff');
      onInvited();
    },
    onError: (mutationError) => {
      setError(mutationError instanceof ApiError ? mutationError.message : 'Unable to send invitation.');
    },
  });

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Invite a staff member">
      <form
        className="flex flex-col gap-4"
        onSubmit={(event) => {
          event.preventDefault();
          setError(null);
          mutation.mutate();
        }}
        noValidate
      >
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-medium text-neutral-700">Name (optional)</span>
          <input
            className="h-10 rounded-md border border-neutral-300 px-3 text-sm"
            value={name}
            onChange={(event) => setName(event.target.value)}
            disabled={mutation.isPending}
          />
        </label>
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-medium text-neutral-700">Email</span>
          <input
            type="email"
            required
            className="h-10 rounded-md border border-neutral-300 px-3 text-sm"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            disabled={mutation.isPending}
          />
        </label>
        <Select
          label="Role"
          value={role}
          onChange={(event) => setRole(event.target.value)}
          options={INVITABLE_ROLES.map((r) => ({ value: r, label: r }))}
          disabled={mutation.isPending}
        />

        {error && (
          <p role="alert" className="text-danger-600 text-sm">
            {error}
          </p>
        )}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose} disabled={mutation.isPending}>
            Cancel
          </Button>
          <Button type="submit" isLoading={mutation.isPending}>
            Send invitation
          </Button>
        </div>
      </form>
    </Modal>
  );
}
