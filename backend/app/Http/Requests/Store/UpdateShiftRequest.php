<?php

namespace App\Http\Requests\Store;

use App\Enums\ShiftStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a rostered shift — moving it, reassigning it, or cancelling it.
 * Every field is optional so the scheduler's drag-to-move interaction can
 * send just the date and times it changed.
 */
class UpdateShiftRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'shift_date' => ['sometimes', 'date_format:Y-m-d'],
            'starts_at' => ['sometimes', 'date_format:H:i'],
            'ends_at' => ['sometimes', 'date_format:H:i'],
            'status' => ['sometimes', Rule::enum(ShiftStatus::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
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

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] .= ':00';
            }
        }

        return $key === null ? $validated : data_get($validated, $key, $default);
    }
}
