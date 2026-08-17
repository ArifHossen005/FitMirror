import { DataTable, type DataTableColumn, Input, Select } from '@fitmirror/ui';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { type AuditLogEntry, fetchAuditLog } from '../../lib/auditLog';
import { toPaginationMeta } from '../../lib/staff';

const MODULE_OPTIONS = [
  { value: '', label: 'All modules' },
  { value: 'tenant', label: 'Tenant' },
  { value: 'user', label: 'Staff' },
  { value: 'impersonation', label: 'Impersonation' },
];

const ACTION_OPTIONS = [
  { value: '', label: 'All actions' },
  { value: 'created', label: 'Created' },
  { value: 'updated', label: 'Updated' },
  { value: 'deleted', label: 'Deleted' },
];

export function AuditLogPage() {
  const [page, setPage] = useState(1);
  const [module, setModule] = useState('');
  const [action, setAction] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const query = useQuery({
    queryKey: ['audit-log', page, module, action, dateFrom, dateTo],
    queryFn: () =>
      fetchAuditLog({
        page,
        module: module || undefined,
        action: action || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      }),
  });

  const columns: DataTableColumn<AuditLogEntry>[] = [
    {
      key: 'created_at',
      header: 'When',
      accessor: (row) => (row.created_at ? new Date(row.created_at).toLocaleString() : '—'),
    },
    {
      key: 'causer',
      header: 'Who',
      accessor: (row) => row.causer?.name ?? row.causer?.email ?? 'System',
    },
    { key: 'module', header: 'Module', accessor: (row) => row.module ?? '—' },
    { key: 'action', header: 'Action', accessor: (row) => row.action ?? '—' },
    { key: 'description', header: 'Description' },
    {
      key: 'subject',
      header: 'Subject',
      accessor: (row) => (row.subject_type ? `${row.subject_type} #${row.subject_id}` : '—'),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Activity log</h1>
        <p className="mt-1 text-sm text-neutral-500">Every change made to your team and account settings.</p>
      </div>

      <div className="flex flex-wrap gap-3">
        <Select
          value={module}
          onChange={(event) => {
            setModule(event.target.value);
            setPage(1);
          }}
          options={MODULE_OPTIONS}
          className="w-40"
        />
        <Select
          value={action}
          onChange={(event) => {
            setAction(event.target.value);
            setPage(1);
          }}
          options={ACTION_OPTIONS}
          className="w-36"
        />
        <Input
          type="date"
          value={dateFrom}
          onChange={(event) => {
            setDateFrom(event.target.value);
            setPage(1);
          }}
          className="w-40"
          aria-label="From date"
        />
        <Input
          type="date"
          value={dateTo}
          onChange={(event) => {
            setDateTo(event.target.value);
            setPage(1);
          }}
          className="w-40"
          aria-label="To date"
        />
      </div>

      <DataTable
        columns={columns}
        data={query.data?.data ?? []}
        getRowId={(row) => row.id}
        isLoading={query.isLoading}
        emptyMessage="No activity recorded yet."
        pagination={toPaginationMeta(query.data)}
        onPageChange={setPage}
      />
    </div>
  );
}
