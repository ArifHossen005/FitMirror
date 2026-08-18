<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Support\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders resources/views/pdf/invoice.blade.php via dompdf and saves it to
 * the `tenant` disk (config/filesystems.php — local in dev, S3/R2 in
 * production) under TenantStorage::path(), so a tenant's invoices live
 * alongside their other media at `tenants/{id}/invoices/{number}.pdf`.
 */
class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'tenant']);

        $path = TenantStorage::path($invoice->tenant, "invoices/{$invoice->number}.pdf");

        Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->save($path, 'tenant');

        return $path;
    }
}
