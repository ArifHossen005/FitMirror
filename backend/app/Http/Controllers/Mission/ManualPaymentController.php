<?php

namespace App\Http\Controllers\Mission;

use App\Enums\BillingCycle;
use App\Enums\SuperAdminPermission;
use App\Http\Requests\Mission\RecordManualPaymentRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/mission/tenants/{tenant}/payments — records a payment
 * collected outside SSLCommerz (bank transfer, cash, a manually-arranged
 * deal). $tenant is resolved via Tenant::query()->findOrFail() directly
 * (not withoutTenantScope() — Tenant itself carries no TenantScope, see
 * its own class docblock: "this model is the one exception").
 */
class ManualPaymentController extends BaseMissionController
{
    public function store(RecordManualPaymentRequest $request, int $tenant, PaymentService $payments): JsonResponse
    {
        $superAdmin = $this->superAdmin($request);

        if (!$superAdmin->hasPermission(SuperAdminPermission::Billing)) {
            return $this->error(
                trans('common.unauthorized'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'unauthorized',
            );
        }

        $tenantModel = Tenant::query()->findOrFail($tenant);
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));
        $cycle = BillingCycle::from($request->validated('billing_cycle'));

        $payment = $payments->recordOffline(
            $tenantModel,
            $plan,
            $cycle,
            $request->validated('amount'),
            $superAdmin,
            $request->validated('note'),
        );

        return $this->created([
            'id' => $payment->id,
            'invoice_number' => $payment->invoiceUnscoped()->number,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
        ], 'Payment recorded and invoice marked paid.');
    }
}
