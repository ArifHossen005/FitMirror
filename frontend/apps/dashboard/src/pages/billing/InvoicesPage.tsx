import { Button, DataTable, type DataTableColumn, toast } from '@fitmirror/ui';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { downloadInvoice, fetchInvoices, type InvoiceSummary, toPaginationMeta } from '../../lib/billing';

const STATUS_CLASSES: Record<string, string> = {
  paid: 'text-success-700 bg-success-50',
  pending: 'bg-warning-50 text-warning-700',
  void: 'bg-neutral-100 text-neutral-600',
  refunded: 'bg-neutral-100 text-neutral-600',
};

export function InvoicesPage() {
  const [page, setPage] = useState(1);
  const [downloadingId, setDownloadingId] = useState<number | null>(null);

  const invoicesQuery = useQuery({
    queryKey: ['billing', 'invoices', page],
    queryFn: () => fetchInvoices(page),
  });

  async function handleDownload(invoice: InvoiceSummary) {
    setDownloadingId(invoice.id);
    try {
      await downloadInvoice(invoice.id, `${invoice.number}.pdf`);
    } catch {
      toast.error('Unable to download this invoice. It may still be generating — try again shortly.');
    } finally {
      setDownloadingId(null);
    }
  }

  const columns: DataTableColumn<InvoiceSummary>[] = [
    { key: 'number', header: 'Invoice #' },
    { key: 'type', header: 'Type', render: (row) => (row.type === 'plan' ? 'Plan' : 'Add-on') },
    { key: 'total', header: 'Total', render: (row) => `${row.total} ${row.currency}` },
    {
      key: 'status',
      header: 'Status',
      render: (row) => (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[row.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
          {row.status}
        </span>
      ),
    },
    {
      key: 'issued_at',
      header: 'Issued',
      accessor: (row) => (row.issued_at ? new Date(row.issued_at).toLocaleDateString() : '—'),
    },
    {
      key: 'actions',
      header: '',
      className: 'text-right',
      render: (row) =>
        row.downloadable ? (
          <Button
            variant="ghost"
            size="sm"
            isLoading={downloadingId === row.id}
            onClick={() => void handleDownload(row)}
          >
            Download
          </Button>
        ) : (
          <span className="text-xs text-neutral-400">Generating…</span>
        ),
    },
  ];

  return (
    <div className="flex flex-col gap-6 p-6">
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Invoices</h1>
        <p className="mt-1 text-sm text-neutral-500">Every plan and add-on invoice for your account.</p>
      </div>

      <DataTable
        columns={columns}
        data={invoicesQuery.data?.data ?? []}
        getRowId={(row) => row.id}
        isLoading={invoicesQuery.isLoading}
        emptyMessage="No invoices yet."
        pagination={toPaginationMeta(invoicesQuery.data)}
        onPageChange={setPage}
      />
    </div>
  );
}
