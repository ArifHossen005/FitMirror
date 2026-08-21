<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class TransferStockRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'from_store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where('tenant_id', $tenantId)],
            'to_store_id' => ['required', 'integer', 'different:from_store_id', Rule::exists('stores', 'id')->where('tenant_id', $tenantId)],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
