<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/v1/billing/invoices and /api/v1/billing/invoices/{invoice}/download
 * — `{invoice}` uses Laravel's normal implicit route-model binding, which
 * (unlike the gateway callbacks and Mission Control controllers elsewhere
 * in the billing module) correctly goes through Invoice's own TenantScope
 * here: this route runs inside an authenticated tenant request, which
 * ResolveTenant has already set an ambient TenantContext for, so a
 * cross-tenant invoice id 404s exactly like any other scoped lookup.
 */
class InvoiceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('billing.view')) {
            return $this->error(trans('common.unauthorized'), Response::HTTP_FORBIDDEN, errorCode: 'unauthorized');
        }

        $invoices = Invoice::query()
            ->orderByDesc('issued_at')
            ->paginate($this->perPage($request));

        return $this->paginated($invoices->through(fn (Invoice $invoice) => [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'type' => $invoice->subscription_id !== null ? 'plan' : 'addon',
            'subtotal' => $invoice->subtotal,
            'discount' => $invoice->discount,
            'vat' => $invoice->vat,
            'total' => $invoice->total,
            'currency' => $invoice->currency,
            'status' => $invoice->status->value,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'downloadable' => $invoice->pdf_path !== null,
        ]));
    }

    public function download(Request $request, Invoice $invoice): StreamedResponse|JsonResponse
    {
        if (!$request->user()->can('billing.view')) {
            return $this->error(trans('common.unauthorized'), Response::HTTP_FORBIDDEN, errorCode: 'unauthorized');
        }

        if (!$invoice->pdf_path || !Storage::disk('tenant')->exists($invoice->pdf_path)) {
            return $this->error(
                'This invoice\'s PDF has not been generated yet.',
                Response::HTTP_NOT_FOUND,
                errorCode: 'invoice_pdf_not_ready',
            );
        }

        return Storage::disk('tenant')->download($invoice->pdf_path, "{$invoice->number}.pdf");
    }
}
