<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\AddonStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Billing\PurchaseAddonRequest;
use App\Models\Addon;
use App\Services\Billing\AddonPurchaseService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/v1/billing/addons (the marketplace catalog) and
 * POST /api/v1/billing/addons/{addon}/purchase — the add-on checkout,
 * reusing the exact SSLCommerz pipeline PaymentService uses for plan
 * purchases (App\Services\Billing\AddonPurchaseService, itself built on
 * GatewayCheckoutService).
 */
class AddonController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $addons = Addon::query()
            ->where('status', AddonStatus::Active)
            ->orderBy('sort_order')
            ->get();

        return $this->success($addons->map(fn (Addon $addon) => [
            'id' => $addon->id,
            'code' => $addon->code,
            'name' => $addon->name,
            'description' => $addon->description,
            'type' => $addon->type->value,
            'price' => $addon->price,
            'currency' => $addon->currency,
            'unit_amount' => $addon->unit_amount,
        ]));
    }

    public function purchase(
        PurchaseAddonRequest $request,
        Addon $addon,
        AddonPurchaseService $addons,
    ): JsonResponse {
        $user = $request->user();

        if (!$user->isTenantOwner()) {
            return $this->error(trans('common.unauthorized'), Response::HTTP_FORBIDDEN, errorCode: 'unauthorized');
        }

        $quantity = (int) ($request->validated('quantity') ?? 1);

        $result = $addons->initiate($user->tenant, $user, $addon, $quantity);

        return $this->success([
            'invoice_number' => $result['invoice']->number,
            'amount' => $result['invoice']->total,
            'currency' => $result['invoice']->currency,
            'gateway_url' => $result['gateway_url'],
        ], 'Redirect the customer to gateway_url to complete payment.');
    }
}
