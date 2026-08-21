<?php

namespace App\Http\Requests\Kiosk;

use App\Http\Requests\BaseFormRequest;
use App\Models\KioskDevice;
use Illuminate\Validation\Rule;

/**
 * Display settings for one kiosk. Every key is optional and merged over
 * what is stored (KioskPairingService::updateSettings()), so the settings
 * form can send only the field the staff member changed.
 */
class UpdateKioskSettingsRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'language' => ['sometimes', Rule::in(KioskDevice::SUPPORTED_LANGUAGES)],
            'theme' => ['sometimes', Rule::in(KioskDevice::SUPPORTED_THEMES)],
            'idle_timeout_seconds' => [
                'sometimes',
                'integer',
                'between:' . KioskDevice::MIN_IDLE_TIMEOUT_SECONDS . ',' . KioskDevice::MAX_IDLE_TIMEOUT_SECONDS,
            ],
            'screensaver_playlist' => ['sometimes', 'array', 'max:50'],
            'screensaver_playlist.*' => ['string', 'max:2048'],
            'show_branding' => ['sometimes', 'boolean'],
            'attract_loop_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
