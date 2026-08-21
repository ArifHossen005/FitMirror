<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Store\ShiftScheduleRequest;
use App\Http\Requests\Store\StoreShiftRequest;
use App\Http\Requests\Store\UpdateShiftRequest;
use App\Models\Shift;
use App\Models\Store;
use App\Services\Store\ShiftService;
use Illuminate\Http\JsonResponse;

/**
 * Staff rostering. Shifts are created against a branch ({store}) but read
 * back through the flat /shifts/schedule listing, because the weekly grid
 * spans every branch a manager oversees — asking per branch would mean one
 * request per column.
 *
 * The listing is deliberately not paginated: it is bounded by the date
 * range instead (capped in ShiftScheduleRequest), and a calendar grid that
 * silently dropped its last few cells onto page two would be wrong rather
 * than merely truncated.
 */
class ShiftController extends BaseApiController
{
    public function __construct(private readonly ShiftService $shifts) {}

    public function schedule(ShiftScheduleRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Store::class);

        $filters = $request->validated();

        $shifts = $this->shifts->schedule(
            $filters['from'],
            $filters['to'],
            isset($filters['store_id']) ? (int) $filters['store_id'] : null,
            isset($filters['user_id']) ? (int) $filters['user_id'] : null,
        );

        return $this->success([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'shifts' => $shifts->map(fn (Shift $shift) => $this->present($shift))->all(),
        ]);
    }

    public function store(StoreShiftRequest $request, Store $store): JsonResponse
    {
        $this->authorize('manageShifts', $store);

        $shift = $this->shifts->create($store, $request->user(), $request->validated());

        return $this->created($this->present($shift->load(['staff:id,name,email', 'store:id,name,code'])));
    }

    public function update(UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        $this->authorize('manageShifts', $shift->store);

        $updated = $this->shifts->update($shift, $request->validated());

        return $this->success($this->present($updated->load(['staff:id,name,email', 'store:id,name,code'])));
    }

    public function cancel(Shift $shift): JsonResponse
    {
        $this->authorize('manageShifts', $shift->store);

        $cancelled = $this->shifts->cancel($shift);

        return $this->success($this->present($cancelled->load(['staff:id,name,email', 'store:id,name,code'])));
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('manageShifts', $shift->store);

        $this->shifts->delete($shift);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'store_id' => $shift->store_id,
            'store_name' => $shift->store?->name,
            'user_id' => $shift->user_id,
            'staff_name' => $shift->staff?->name,
            'staff_email' => $shift->staff?->email,
            'shift_date' => $shift->shift_date->format('Y-m-d'),
            'starts_at' => substr((string) $shift->starts_at, 0, 5),
            'ends_at' => substr((string) $shift->ends_at, 0, 5),
            'is_overnight' => $shift->endsAtInstant()->format('Y-m-d') !== $shift->shift_date->format('Y-m-d'),
            'duration_minutes' => $shift->durationMinutes(),
            'status' => $shift->status->value,
            'status_label' => $shift->status->label(),
            'note' => $shift->note,
            'created_at' => $shift->created_at?->toIso8601String(),
        ];
    }
}
