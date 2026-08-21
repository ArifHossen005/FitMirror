<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\BaseFormRequest;

/**
 * Creating a rostered shift.
 *
 * `ends_at` carries no `after:starts_at` rule on purpose: an overnight
 * shift legitimately ends at a time earlier in the day than it starts
 * (22:00 to 06:00). Duration sanity and double-booking are both decided in
 * ShiftService, which reads the pair as absolute instants rather than as
 * two clock faces.
 */
class StoreShiftRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'shift_date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * MySQL TIME columns want H:i:s; the scheduler sends H:i.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        $validated['starts_at'] .= ':00';
        $validated['ends_at'] .= ':00';

        return $key === null ? $validated : data_get($validated, $key, $default);
    }
}
