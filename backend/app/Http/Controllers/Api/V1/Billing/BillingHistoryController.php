<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/v1/billing/history — payments and refunds merged into one
 * chronological feed. Both are small per-tenant tables (one row per
 * gateway attempt / refund, not per day), so pulling every row for the
 * tenant and paginating the merged, sorted collection in memory is simple
 * and fast enough here — unlike a genuinely large table, this never
 * justifies a UNION query or a denormalized ledger table of its own.
 */
class BillingHistoryController extends BaseApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!$request->user()->can('billing.view')) {
            return $this->error(trans('common.unauthorized'), Response::HTTP_FORBIDDEN, errorCode: 'unauthorized');
        }

        $payments = Payment::query()->get()->map(fn (Payment $payment) => [
            'type' => 'payment',
            'id' => $payment->id,
            'gateway' => $payment->gateway->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'created_at' => $payment->created_at,
        ]);

        $refunds = Refund::query()->get()->map(fn (Refund $refund) => [
            'type' => 'refund',
            'id' => $refund->id,
            'gateway' => null,
            'amount' => $refund->amount,
            'currency' => null,
            'status' => $refund->status->value,
            'created_at' => $refund->created_at,
        ]);

        $combined = $payments->concat($refunds)->sortByDesc('created_at')->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = $this->perPage($request);

        $slice = $combined->forPage($page, $perPage)->map(fn (array $row) => [
            ...$row,
            'created_at' => $row['created_at']?->toIso8601String(),
        ])->values();

        $paginator = new LengthAwarePaginator($slice, $combined->count(), $perPage, $page);

        return $this->paginated($paginator);
    }
}
