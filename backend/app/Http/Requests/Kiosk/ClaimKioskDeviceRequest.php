<?php

namespace App\Http\Requests\Kiosk;

use App\Http\Requests\BaseFormRequest;
use App\Models\KioskDevice;

/**
 * Sent by the kiosk itself, unauthenticated, to redeem a pairing code for
 * a device token.
 *
 * `exists` is deliberately not used on the pairing code: an existence rule
 * would answer "is this a real code" to an anonymous caller, turning the
 * endpoint into an oracle for enumerating live codes. Whether the code
 * resolves is decided in KioskPairingService::claim(), which returns the
 * same message for a wrong code and an expired one.
 */
class ClaimKioskDeviceRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pairing_code' => [
                'required',
                'string',
                'size:' . KioskDevice::PAIRING_CODE_LENGTH,
            ],
            // A stable per-device identifier the kiosk generates once and
            // persists locally. Not a secret and not trusted for
            // authentication — it exists so the dashboard can show which
            // physical machine claimed a code.
            'device_fingerprint' => ['required', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }
}
