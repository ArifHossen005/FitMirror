<?php

namespace App\Http\Requests\Kiosk;

use App\Http\Requests\BaseFormRequest;
use App\Models\KioskDevice;
use Illuminate\Validation\Rule;

/**
 * Registering a kiosk from the dashboard. The device itself supplies
 * nothing at this point — it has not been paired yet — so this carries
 * only what a staff member types: a name, and optionally the display
 * settings to start it on.
 */
class RegisterKioskDeviceRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'settings' => ['sometimes', 'array:' . implode(',', array_keys(KioskDevice::DEFAULT_SETTINGS))],
            'settings.language' => ['sometimes', Rule::in(KioskDevice::SUPPORTED_LANGUAGES)],
            'settings.theme' => ['sometimes', Rule::in(KioskDevice::SUPPORTED_THEMES)],
            'settings.idle_timeout_seconds' => [
                'sometimes',
                'integer',
                'between:' . KioskDevice::MIN_IDLE_TIMEOUT_SECONDS . ',' . KioskDevice::MAX_IDLE_TIMEOUT_SECONDS,
            ],
            'settings.screensaver_playlist' => ['sometimes', 'array', 'max:50'],
            'settings.screensaver_playlist.*' => ['string', 'max:2048'],
            'settings.show_branding' => ['sometimes', 'boolean'],
            'settings.attract_loop_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
