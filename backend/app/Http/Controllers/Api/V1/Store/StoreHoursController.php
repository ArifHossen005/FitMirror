<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Store\UpdateStoreHoursRequest;
use App\Models\Store;
use App\Models\StoreHour;
use App\Services\Store\StoreHoursService;
use Illuminate\Http\JsonResponse;

/**
 * Weekly opening hours and the kiosk-active window for one branch.
 *
 * The response always carries all seven days, filling in the ones the
 * tenant has never configured, so the editor renders a complete week
 * without having to invent rows client-side and the two representations
 * cannot drift.
 */
class StoreHoursController extends BaseApiController
{
    public function __construct(private readonly StoreHoursService $hours) {}

    public function show(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        return $this->success($this->present($store));
    }

    public function update(UpdateStoreHoursRequest $request, Store $store): JsonResponse
    {
        $this->authorize('manageHours', $store);

        $this->hours->replaceWeek($store, $request->days());

        return $this->success($this->present($store->fresh()), 'Opening hours updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Store $store): array
    {
        $stored = $this->hours->forStore($store)->keyBy('day_of_week');

        $days = [];

        foreach (StoreHour::DAY_NAMES as $index => $dayName) {
            $row = $stored->get($index);
            $hasRow = $row instanceof StoreHour;

            $days[] = [
                'day_of_week' => $index,
                'day_name' => $dayName,
                // A day with no row is reported as configured-closed rather
                // than omitted, so the editor has a value for every control.
                // Note this is not how the kiosk guard reads an *entirely*
                // unconfigured branch — see StoreHoursService's docblock.
                'is_closed' => $hasRow ? $row->is_closed : true,
                'opens_at' => $hasRow ? $row->opens_at : null,
                'closes_at' => $hasRow ? $row->closes_at : null,
                'kiosk_opens_at' => $hasRow ? $row->kiosk_opens_at : null,
                'kiosk_closes_at' => $hasRow ? $row->kiosk_closes_at : null,
            ];
        }

        return [
            'store_id' => $store->id,
            'timezone' => $store->timezone,
            'is_configured' => $stored->isNotEmpty(),
            'days' => $days,
            'kiosk_availability' => $this->hours->availability($store),
        ];
    }
}
