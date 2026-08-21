<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Catalog\StoreAttributeValueRequest;
use App\Http\Requests\Catalog\UpdateAttributeValueRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Services\Catalog\AttributeValueService;
use Illuminate\Http\JsonResponse;

/**
 * Values nested under a parent Attribute — gated by AttributePolicy since
 * a value has no independent identity a Manager would manage separately
 * from its attribute (matching how StoreHoursController is gated by
 * StorePolicy::manageHours() rather than its own policy).
 */
class AttributeValueController extends BaseApiController
{
    public function __construct(private readonly AttributeValueService $values) {}

    public function store(StoreAttributeValueRequest $request, Attribute $attribute): JsonResponse
    {
        $this->authorize('update', $attribute);

        $value = $this->values->create($attribute, $request->validated());

        return $this->created($this->present($value), 'Attribute value created successfully.');
    }

    public function update(UpdateAttributeValueRequest $request, Attribute $attribute, AttributeValue $value): JsonResponse
    {
        $this->authorize('update', $attribute);
        $this->assertBelongsToAttribute($attribute, $value);

        $updated = $this->values->update($value, $request->validated());

        return $this->success($this->present($updated), 'Attribute value updated successfully.');
    }

    public function destroy(Attribute $attribute, AttributeValue $value): JsonResponse
    {
        $this->authorize('update', $attribute);
        $this->assertBelongsToAttribute($attribute, $value);

        $this->values->delete($value);

        return $this->noContent();
    }

    private function assertBelongsToAttribute(Attribute $attribute, AttributeValue $value): void
    {
        abort_if($value->attribute_id !== $attribute->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AttributeValue $value): array
    {
        return [
            'id' => $value->id,
            'attribute_id' => $value->attribute_id,
            'value' => $value->value,
            'hex_color' => $value->hex_color,
            'sort_order' => $value->sort_order,
        ];
    }
}
