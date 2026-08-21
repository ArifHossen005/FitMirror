<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Product;
use App\Services\Product\SizeChartService;
use Illuminate\Http\JsonResponse;

/**
 * The kiosk-facing half of Phase 5.B's size-chart work — the flat popup
 * payload a shopper taps "size guide" to see. Reachable under the same
 * `kiosk.auth` middleware as KioskSessionController's other endpoints
 * (routes/api_v1.php), so it is authenticated by device token, never a
 * user session — see App\Http\Middleware\AuthenticateKioskDevice.
 */
class SizeChartController extends BaseApiController
{
    public function __construct(private readonly SizeChartService $sizeCharts) {}

    public function show(Product $product): JsonResponse
    {
        return $this->success(['size_charts' => $this->sizeCharts->kioskPayload($product)]);
    }
}
