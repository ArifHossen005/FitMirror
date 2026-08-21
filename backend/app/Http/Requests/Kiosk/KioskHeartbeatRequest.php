<?php

namespace App\Http\Requests\Kiosk;

use App\Http\Requests\BaseFormRequest;

/**
 * Periodic check-in from a paired kiosk. Everything is optional — the
 * heartbeat's primary job is simply arriving, which is what updates
 * `last_seen_at`; the health payload is extra detail the dashboard shows
 * when the device chooses to report it.
 *
 * The health keys are enumerated rather than accepted as free-form JSON so
 * a buggy kiosk build cannot grow the column without bound, while still
 * leaving room for Phase 6's render-pipeline metrics to be added by name.
 */
class KioskHeartbeatRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_version' => ['nullable', 'string', 'max:32'],
            'health' => ['sometimes', 'array:camera_ok,network_ok,storage_free_mb,battery_percent,last_error'],
            'health.camera_ok' => ['sometimes', 'boolean'],
            'health.network_ok' => ['sometimes', 'boolean'],
            'health.storage_free_mb' => ['sometimes', 'integer', 'min:0'],
            'health.battery_percent' => ['sometimes', 'integer', 'between:0,100'],
            'health.last_error' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
